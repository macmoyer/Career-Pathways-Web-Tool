<?php
chdir("..");
require_once("inc.php");
require_once("POSTChart.inc.php");

$MODE = 'post';
ModuleInit('post_drawings');


if (KeyInRequest('action')) {
	$action = $_REQUEST['action'];
	switch ($action) {
		case 'copy_version':
			copyVersion(intval($_REQUEST['version_id']));
			die();
			break;
		case 'view':
		case 'draw':
			showVersion();
			die();
			break;
		case 'version_info':
			showVersionInfo();
			die();
			break;
		case 'drawing_info':
			showDrawingInfo();
			die();
			break;
		case 'new_drawing_form':
			showNewDrawingForm();
			die();
			break;
		case 'config':
			processConfigRequest();
			die();
			break;
		case 'drawing_list':
			// used to select a drawing in the connections chooser
			processDrawingListRequest();
			die();
	}
}


if( KeyInRequest('drawing_id') ) {
	$drawing_id = intval($_REQUEST['drawing_id']);

	if( PostRequest() ) {

		// permissions check
		$drawing = GetDrawingInfo($drawing_id, 'post');
		if( !is_array($drawing) || (!IsAdmin() && $_SESSION['school_id'] != $drawing['school_id']) ) {
			header("Location: ".$_SERVER['PHP_SELF']);
			die();
		}

		if( Request('action') == 'delete' ) {
			if( is_array($drawing) ) {
				if( IsSchoolAdmin() || $drawing['frozen'] == 0 ) {
					// school admins can delete versions, and anyone can delete a version if it has never been committed
					$DB->Query("UPDATE post_drawings SET deleted=1 WHERE id=$drawing_id");
				}
			}
			header("Location: ".$_SERVER['PHP_SELF']);
			die();
		}

		if( Request('action') == 'publish' ) {
			$drawing = $DB->SingleQuery("SELECT * FROM post_drawings WHERE id=$drawing_id");
			if( is_array($drawing) ) {
				$DB->Query("UPDATE post_drawings SET published=0 WHERE parent_id=".$drawing['parent_id']);
				$DB->Query("UPDATE post_drawings SET published=1, frozen=1 WHERE id=$drawing_id");
			}
		}

		header("Location: ".$_SERVER['PHP_SELF'].'?action=drawing_info&id='.$drawing['parent_id']);

	} else {
		// support for old-school urls
		if (KeyInRequest('draw')) {
			header("Location: ".$_SERVER['PHP_SELF']."?action=draw&version_id=".$drawing_id);
		}
		if (KeyInRequest('view')) {
			header("Location: ".$_SERVER['PHP_SELF']."?action=view&version_id=".$drawing_id);
		}
		else {
			header("Location: ".$_SERVER['PHP_SELF']."?action=version_info&version_id=".$drawing_id);
		}
	}

} elseif( KeyInRequest('id') ) {

	if( PostRequest() ) {

		$drawing = $DB->SingleQuery("SELECT *
			FROM post_drawings, post_drawing_main
			WHERE post_drawings.parent_id=post_drawing_main.id
			AND post_drawing_main.id=".intval(Request('id')));
		if( !(Request('id') == "" || is_array($drawing) && (IsAdmin() || $drawing['school_id'] == $_SESSION['school_id'])) ) {
			// permissions error
			header("Location: ".$_SERVER['PHP_SELF']);
			die();
		}

		if( Request('delete') == 'delete' ) {
			$drawing_id = intval($_REQUEST['id']);
			if( CanDeleteDrawing($drawing_id) ) {
				// when deleting the entire drawing (from drawing_main) actually remove the records
						
				$DB->Query("DELETE FROM post_drawings WHERE parent_id=".$drawing_id);
				$DB->Query("DELETE FROM post_drawing_main WHERE id=".$drawing_id);
				$DB->Query("DELETE FROM post_cell WHERE drawing_id=".$drawing_id);
				$DB->Query("DELETE FROM post_col WHERE drawing_id=".$drawing_id);
				header("Location: ".$_SERVER['PHP_SELF']);
			}
			die();
		}

		$content = array();
		$content['name'] = $_REQUEST['name'];
		$content['last_modified'] = $DB->SQLDate();
		$content['last_modified_by'] = $_SESSION['user_id'];

		$school_id = (IsAdmin()?$_REQUEST['school_id']:$_SESSION['school_id']);

		$content['code'] = CreateDrawingCodeFromTitle($content['name'],$school_id);

		if( Request('id') ) {
			// update requests are only handled through drawings_post.php now.
		} else {
			$content['date_created'] = $DB->SQLDate();
			$content['created_by'] = $_SESSION['user_id'];
			$content['school_id'] = $school_id;
			$content['skillset_id'] = Request('skillset_id');
			$content['type'] = Request('type');
			$parent_id = $DB->Insert('post_drawing_main',$content);

			// create the first default drawing
			$content = array();
			$content['version_num'] = 1;
			$content['date_created'] = $DB->SQLDate();
			$content['created_by'] = $_SESSION['user_id'];
			$content['last_modified'] = $DB->SQLDate();
			$content['last_modified_by'] = $_SESSION['user_id'];
			$content['parent_id'] = $parent_id;

			if( Request('type') == 'cc' )
			{
				$content['num_rows'] = intval(Request('num_terms'));
				$content['num_extra_rows'] = intval(Request('num_extra_rows'));
			}
			else
			{
				$content['num_rows'] = 4;
				$content['num_extra_rows'] = 1;
			}

			$drawing_id = $DB->Insert('post_drawings',$content);

			if( Request('type') == 'cc' )
			{
				// create n empty column headers
				for( $i=0; $i<intval(Request('num_columns')); $i++ )
				{
					$col = array();
					$col['drawing_id'] = $drawing_id;
					$col['title'] = '';
					$col['num'] = $i+1;
					$colmap[$i+1] = $DB->Insert('post_col', $col);
				}
				$num_cols = intval(Request('num_columns'));
			}
			else
			{
				// copy the default columns to this drawing
				$cols = $DB->MultiQuery('SELECT * FROM post_default_col WHERE school_id='.$school_id.' ORDER BY num');
				foreach( $cols as $c )
				{
					$col = array();
					$col['drawing_id'] = $drawing_id;
					$col['title'] = $c['title'];
					$col['num'] = $c['num'];
					$colmap[$c['num']] = $DB->Insert('post_col', $col);
				}
				$num_cols = count($cols);
			}

			// now create all the empty cells for the drawing
			for( $x=1; $x<=$num_cols; $x++ )
			{
				for( $y=1; $y<=$content['num_rows']; $y++ )
				{
					$cell = array();
					$cell['drawing_id'] = $drawing_id;
					$cell['row_num'] = $y;
					$cell['col_id'] = $colmap[$x];
					$DB->Insert('post_cell', $cell);
				}
				for( $y=100; $y<$content['num_extra_rows']+100; $y++ )
				{
					$cell = array();
					$cell['drawing_id'] = $drawing_id;
					$cell['row_num'] = $y;
					$cell['col_id'] = $colmap[$x];
					$DB->Insert('post_cell', $cell);
				}
				
			}

			// start drawing it
			header("Location: ".$_SERVER['PHP_SELF']."?action=draw&version_id=".$drawing_id);
		}

	} else {
		// support for old-school urls
		if (!Request('id')) {
			header("Location: ".$_SERVER['PHP_SELF'] . '?action=new_drawing_form');
		}
		else {
			header("Location: ".$_SERVER['PHP_SELF'] . '?action=drawing_info&id=' . $_REQUEST['id']);
		}
	}

} else {
	require('view/drawings/list.php');
}
















function showVersion() {
	global $DB, $TEMPLATE;


	$drawing = $DB->SingleQuery('SELECT main.*, d.published, d.frozen, schools.school_abbr, d.id
		FROM post_drawing_main AS main, post_drawings AS d, schools
		WHERE d.parent_id = main.id
			AND main.school_id = schools.id
			AND d.id = '.intval(Request('version_id')).'
			AND deleted = 0');
	if( !is_array($drawing) ) {
		echo "The record does not exist";
		die();
	}

	$TEMPLATE->addl_styles[] = "/c/pstyle.css";
	$TEMPLATE->addl_styles[] = "/files/greybox/greybox.css";

	$TEMPLATE->addl_scripts[] = '/common/jquery-1.3.min.js';
	$TEMPLATE->addl_scripts[] = '/files/greybox.js';
	$TEMPLATE->addl_scripts[] = '/common/URLfunctions1.js';


	if(CanEditVersion($drawing['id'])) {
		$readonly = false;
	} else {
		$readonly = true;
	}



	if( $readonly == false ) 
	{
		$TEMPLATE->addl_scripts[] = '/common/jquery/jquery-ui.js';
		$TEMPLATE->addl_scripts[] = '/common/jquery/jquery.base64.js';
		$TEMPLATE->addl_scripts[] = '/c/postedit.js';
	}

	if( !($drawing['published']==1 || $drawing['frozen']==1) ) {
		$TEMPLATE->toolbar_function = "ShowToolbar";
	}
	if( $drawing['frozen'] == 1 ) {
		$TEMPLATE->toolbar_function = "ShowFrozenHelp";
	}
	if( $drawing['published'] == 1 ) {
		$TEMPLATE->toolbar_function = "ShowPublishedHelp";
	}
	if( KeyInRequest('view') ) {
		$TEMPLATE->toolbar_function = "";
	}
	if( $readonly ) {
		$TEMPLATE->toolbar_function = "ShowReadonlyHelp";
	}

	
	PrintHeader();
	
	$post = POSTChart::Create($drawing['id']);

	echo '<div id="post_title"><img src="/files/titles/post/'.base64_encode($post->school_abbr).'/'.base64_encode($post->name).'.png" alt="' . $post->school_abbr . ' Career Pathways - ' . $post->name . '" /></div>';
	
	echo '<div id="canvas">';
	$post->display();
	echo '</div> <!-- end canvas -->';
	
	PrintFooter();

}

function copyVersion($version_id) {
	global $DB;

	$post = POSTChart::create($version_id);
	$post->loadDataFromDB();

	$drawing = GetDrawingInfo($version_id, 'post');

	// first get the title information
	$drawing_main = $DB->SingleQuery("SELECT * FROM post_drawing_main WHERE id=" . $drawing['parent_id']);

	$create = Request('create') ? Request('create') : 'new_version';
	$copy_to = Request('copy_to') ? Request('copy_to') :'same_school';

	if( IsAdmin() ) {
		if( $_SESSION['school_id'] != $drawing['school_id'] ) {
			if ($copy_to !== 'same_school') {
				$different_school = false;
				$create = 'new_drawing';
			}
		} else {
			$different_school = false;
		}
	} else if ($_SESSION['school_id'] !== $drawing['school_id']) {
		$create = 'new_drawing';
		$copy_to = 'user_school';
	}
	else {
		$copy_to = 'same_school';
	}

	if( $create == 'new_drawing' ) {
		if ($copy_to !== 'same_school') {
			$newdrawing['school_id'] = $_SESSION['school_id'];
			$post->setSchoolID($_SESSION['school_id']);
		}
		else {
			$newdrawing['school_id'] = $drawing['school_id'];
			$post->setSchoolID($drawing['school_id']);
		}
		$post->setDrawingName(Request('drawing_name') ? Request('drawing_name') : $drawing_main['name']);

		$new_version_id = $post->saveToDB();
	} else {
		// find the greatest version number for this drawing
		$new_version_id = $post->saveToDB($drawing_main['id']);
	}

	if (Request('from_popup') == 'true') {
		header("Location: /a/copy_success_popup.php?mode=post&version_id=$new_version_id&copy_to=$copy_to&create=$create");
	}
	else {
		header("Location: ".$_SERVER['PHP_SELF']."?action=draw&version_id=".$new_version_id);
	}
}


function showDrawingInfo() {
global $DB, $TEMPLATE;

	$TEMPLATE->AddCrumb('', 'POST Drawing Properties');
	
	PrintHeader();

	$drawing = $DB->SingleQuery("SELECT post_drawing_main.*
		FROM post_drawing_main
		WHERE post_drawing_main.id=".intval(Request('id')));
	if( is_array($drawing) ) {
		if( 1 || IsAdmin() || $drawing['school_id'] == $_SESSION['school_id'] ) {
			ShowDrawingForm(Request('id'));
		} else {
			ShowReadonlyForm(Request('id'));
		}
	} else {
		echo "Not found";
	}

	PrintFooter();
}

function showNewDrawingForm() {
	PrintHeader();
	ShowDrawingForm("");
	PrintFooter();
}

function ShowDrawingForm($id) {
	global $DB, $MODE;
	require('view/drawings/post_drawing_info.php');
}

function showVersionInfo() {
	global $DB, $MODE, $TEMPLATE;

	$TEMPLATE->addl_scripts[] = '/common/jquery-1.3.min.js';
	$TEMPLATE->addl_scripts[] = '/files/greybox.js';
	$TEMPLATE->AddCrumb('', 'POST Version Settings');

	PrintHeader();
	$version_id = Request('version_id');
	require('view/drawings/post_version_info.php');
	PrintFooter();
}

function ShowInfobar() {
	require('view/post/infobar.php');
}

function ShowToolbar() {
	ShowInfobar();

	showToolbarAndHelp(true, 'standard');
}


function ShowPublishedHelp() {
	ShowInfobar();

	showToolbarAndHelp(false, 'published');
}

function ShowFrozenHelp() {
	ShowInfobar();

	showToolbarAndHelp(true, 'frozen');
}

function ShowReadonlyHelp() {
	ShowInfobar();
	showToolbarAndHelp(false, 'read_only');
}

function ShowDrawingListHelp() {
	$helpFile = 'drawing_list';
	require('view/drawings/helpbar.php');
}

function ShowReadonlyForm($id) {
	require('view/drawings/read_only_form.php');
}

function showToolbarAndHelp($publishAllowed, $helpFile = false) {
	require('view/post/toolbar.php');
	require('view/post/helpbar.php');
}

/**
 * This is an ajax handler for configuring rows/columns
 */
function processConfigRequest()
{
	global $DB;

	$drawing_id = intval(Request('drawing_id'));
	
	if( !CanEditVersion($drawing_id) )
		die('Permissions error');
	
	switch( Request('change') )
	{
	case 'terms':
		$old_rows = $DB->GetValue('num_rows', 'post_drawings', $drawing_id);
		$new_rows = intval(Request('value'));
		
		$drawing = array();
		$drawing['num_rows'] = $new_rows;
		$DB->Update('post_drawings', $drawing, $drawing_id);

		if( $new_rows < $old_rows )
		{
			// removing rows. delete some database records.
			$DB->Query('DELETE FROM post_cell 
				WHERE drawing_id='.$drawing_id.'
 				AND row_num>'.($new_rows));
		}
		elseif( $old_rows < $new_rows )
		{
			// adding rows. make some new empty cells.

			$cols = $DB->VerticalQuery('SELECT * FROM post_col WHERE drawing_id='.$drawing_id.' ORDER BY num', 'id', 'num');
			
			for( $i = $old_rows+1; $i <= $new_rows; $i++ )
			{
				foreach( $cols as $num=>$col_id )
				{
					$cell = array();
					$cell['drawing_id'] = $drawing_id;
					$cell['col_id'] = $col_id;
					$cell['row_num'] = $i;
					$DB->Insert('post_cell', $cell);
				}
			}
		
		}
		
		break;
	case 'extra_rows':
		$old_rows = $DB->GetValue('num_extra_rows', 'post_drawings', $drawing_id);
		$new_rows = intval(Request('value'));
		
		$drawing = array();
		$drawing['num_extra_rows'] = $new_rows;
		$DB->Update('post_drawings', $drawing, $drawing_id);

		if( $new_rows < $old_rows )
		{
			// removing rows. delete some database records.
			$DB->Query('DELETE FROM post_cell 
				WHERE drawing_id='.$drawing_id.'
 				AND row_num>='.(100+$new_rows));
		}
		elseif( $old_rows < $new_rows )
		{
			// adding rows. make some new empty cells.

			$cols = $DB->VerticalQuery('SELECT * FROM post_col WHERE drawing_id='.$drawing_id.' ORDER BY num', 'id', 'num');
			
			for( $i = $old_rows; $i < $new_rows; $i++ )
			{
				foreach( $cols as $num=>$col_id )
				{
					$cell = array();
					$cell['drawing_id'] = $drawing_id;
					$cell['col_id'] = $col_id;
					$cell['row_num'] = $i+100;
					$DB->Insert('post_cell', $cell);
				}
			}
		}
		break;
	case 'columns':
		$cols = $DB->VerticalQuery('SELECT * FROM post_col WHERE drawing_id='.$drawing_id.' ORDER BY num', 'id', 'num');
		$drawing = $DB->SingleQuery('SELECT * FROM post_drawings WHERE id='.$drawing_id);

		$old_cols = count($cols);
		$new_cols = intval(Request('value'));
		
		if( $new_cols < $old_cols )
		{
			// removing columns. delete cells vertically
			for( $i = $old_cols; $i > $new_cols; $i-- )
			{
				$DB->Query('DELETE FROM post_col WHERE id='.$cols[$i]);
				$DB->Query('DELETE FROM post_cell WHERE col_id='.$cols[$i]);
			}
		}
		else
		{
			// adding columns. add cells vertically
			
			for( $i = $old_cols+1; $i <= $new_cols; $i++ )
			{
				$col = array();
				$col['drawing_id'] = $drawing_id;
				$col['num'] = $i;
				$col_id = $DB->Insert('post_col', $col);
				
				for( $j=1; $j<=$drawing['num_rows']; $j++ )
				{
					$cell = array();
					$cell['drawing_id'] = $drawing_id;
					$cell['row_num'] = $j;
					$cell['col_id'] = $col_id;
					$DB->Insert('post_cell', $cell);
				}

				for( $j=100; $j<$drawing['num_extra_rows']+100; $j++ )
				{
					$cell = array();
					$cell['drawing_id'] = $drawing_id;
					$cell['row_num'] = $j;
					$cell['col_id'] = $col_id;
					$DB->Insert('post_cell', $cell);
				}

			}
		}
	
		break;	
	}
}


function showNewDrawingPreview(&$config)
{
	global $DB;
	
	
	
	
}


function processDrawingListRequest()
{
	global $DB;

	if( Request('showForm') == 1 )
	{
	?>
	<div id="connectionForm">
		<div style="width:700px;margin:0 auto;">
			<h3>Organizations</h3>
			<?php
			$type = $DB->GetValue('type', 'post_drawing_main', intval(Request('drawing_id')));
			if( $type == 'HS' )
			{
				$schools = $DB->VerticalQuery('SELECT * FROM schools WHERE organization_type!="HS" ORDER BY school_name', 'school_name', 'id');
			}
			else
			{
				$schools = $DB->VerticalQuery('SELECT * FROM schools WHERE organization_type="HS" ORDER BY school_name', 'school_name', 'id');
			}

			echo '<select size="6" id="list_schools" style="width:100%" onchange="select_organization(this.value)">';
			foreach( $schools as $sid=>$school )
			{
				echo '<option value="'.$sid.'">'.$school.'</option>';
			}
			echo '</select>';
			?>
			<br />
			
			<div id="list_of_drawings"></div>
		
			<div id="submit_btn" style="display:none" onclick="save_drawing_selection()">Save</div>
			
			<div id="drawing_preview_box" style="display:none;margin:0 auto;width:700px;height:400px;"><h3>Preview</h3><iframe style="width:700px;height:400px;background-color:#FFFFFF;"></iframe></div>
		</div>
	</div>
	<?php
	}
	elseif( Request('save') == 1 || Request('delete') )
	{
		$drawing_id = intval(Request('drawing_id'));
		$type = $DB->GetValue('type', 'post_drawing_main', $drawing_id);

		$col = ($type == 'HS' ? 'hs_id' : 'cc_id');
		$oth = ($type == 'HS' ? 'cc_id' : 'hs_id');

		if( Request('save') == 1 )
		{
			foreach( explode(',', Request('drawings')) as $link_id )
			{
				$test = $DB->MultiQuery('SELECT * FROM post_conn WHERE '.$col.'='.$drawing_id.' AND '.$oth.'='.$link_id);
				if( count($test) == 0 )
					$DB->Insert('post_conn', array($col=>$drawing_id, $oth=>$link_id));
			}
		}
		elseif( Request('delete') )
		{
			$link_id = intval(Request('delete'));
			$DB->Query('DELETE FROM post_conn WHERE '.$col.'='.$drawing_id.' AND '.$oth.'='.$link_id);
		}
		
		ShowSmallDrawingConnectionList($drawing_id, 'connections', null, array(
			'delete'=>'javascript:remove_connection(%%)'
		));
	}
	else
	{
		// return a formatted list of drawings
		$drawings = $DB->MultiQuery('SELECT M.*, CONCAT(U.first_name," ",U.last_name) AS modified_by
			FROM post_drawing_main M
			JOIN post_drawings D ON D.parent_id=M.id
			LEFT JOIN users U ON M.last_modified_by=U.id
			WHERE M.school_id='.intval(Request('school_id')).'
				AND D.published=1
			ORDER BY name');
		echo '<h3>Drawings</h3>';
		echo '<div style="background-color:#ffffff; border: 1px #999999 solid; padding:4px"><table width="100%">';
		foreach( $drawings as $d )
		{
			echo '<tr class="drawing_select" id="d_'.$d['id'].'">';
				echo '<td width="20" class="icon"><img src="/common/silk/tick.png" width="16" height="16" style="display:none" /></td>';
				echo '<td>' . $d['name'] . '</td>';
				echo '<td width="40" class="preview"><a href="javascript:loadDrawingPreview(\''.$d['code'].'\')"><img src="/common/silk/magnifier.png" width="16" height="16" /></a></td>';
				echo '<td width="155"><span class="fwfont">'.($d['last_modified']==''?'':$DB->Date('Y-m-d f:i a',$d['last_modified'])).'</span></td>';
				echo '<td width="130">' . $d['modified_by'] . '</td>';
			echo '</tr>';
		}
		echo '</table>';
		if( count($drawings) == 0 )
		{
			echo 'No published drawings were found for this school.';
		}
		echo '</div>';
	}	
	
}

?>