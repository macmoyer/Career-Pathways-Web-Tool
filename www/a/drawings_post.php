<?php
chdir("..");
include("inc.php");

ModuleInit('drawings');


if( Request('mode') )
	$mode = Request('mode');
else
	$mode = 'pathways';

if( $mode == 'pathways' )
{
	$main_table = 'drawing_main';
	$version_table = 'drawings';
}
else
{
	$main_table = 'post_drawing_main';
	$version_table = 'post_drawings';
}

if( Request('id') ) {
	// version

	$drawing = $DB->SingleQuery("SELECT *
			FROM $version_table AS v, $main_table AS m
			WHERE v.parent_id=m.id
			AND m.id=".intval(Request('id')));
	if( !(Request('id') == "" || is_array($drawing) && (IsAdmin() || $drawing['school_id'] == $_SESSION['school_id'])) ) {
			// permissions error
			die();
	}

	$school_id = $DB->GetValue('school_id', $main_table, intval($_REQUEST['id']));

	$content = array();
	$content['name'] = $_REQUEST['title'];
	$content['last_modified'] = $DB->SQLDate();
	$content['last_modified_by'] = $_SESSION['user_id'];
	$content['code'] = CreateDrawingCodeFromTitle($content['name'],$school_id,intval($_REQUEST['id']), $mode);

	$DB->Update($main_table,$content,intval($_REQUEST['id']));

	echo $content['code'];
}

if( Request('drawing_id') ) {

	// permissions check
	$drawing = GetDrawingInfo(intval(Request('drawing_id')), $mode);
	if( !is_array($drawing) || (!IsAdmin() && $_SESSION['school_id'] != $drawing['school_id']) ) {
			die();
	}

	if( Request('note') ) {
			$content = array();
			$content['note'] = $_REQUEST['note'];
			$DB->Update($version_table, $content, intval($_REQUEST['drawing_id']));
	}

}

?>