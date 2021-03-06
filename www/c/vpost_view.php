<?php
//PDF call passes session ID via command line call. see /pdf/index.php
if(isset($_GET['session_id'])){
    session_id($_GET['session_id']);
}

chdir("..");
require_once("inc.php");
require_once("POSTChart.inc.php");

if($SITE->hasFeature('oregon_skillset')){
	$drawings = $DB->MultiQuery('SELECT d.*, school_name, school_abbr, v.name AS view_name, version.id AS version_id, tab_name, skillset_id
	        FROM vpost_views AS v
	        JOIN vpost_links AS vl ON v.id = vl.vid
		JOIN post_drawing_main AS d ON vl.post_id=d.id
		JOIN post_drawings AS version ON version.parent_id=d.id
		JOIN schools AS s ON d.school_id=s.id
		LEFT JOIN oregon_skillsets ON v.oregon_skillsets_id = oregon_skillsets.id
		WHERE v.id = '.intval(Request('id')).'
			AND version.published = 1
		ORDER BY vl.sort, vl.tab_name');
} else {
	$drawings = $DB->MultiQuery('SELECT d.*, school_name, school_abbr, v.name AS view_name, version.id AS version_id, tab_name, skillset_id
	        FROM vpost_views AS v
	        JOIN vpost_links AS vl ON v.id = vl.vid
		JOIN post_drawing_main AS d ON vl.post_id=d.id
		JOIN post_drawings AS version ON version.parent_id=d.id
		JOIN schools AS s ON d.school_id=s.id
		WHERE v.id = '.intval(Request('id')).'
			AND version.published = 1
		ORDER BY vl.sort, vl.tab_name');
}
$page_title = 'Not Found';

$hs = array();
$cc = array();
$skillsets = array();
foreach( $drawings as $d )
{
	if( $d['skillset_id'] != '' )
	{
		if( !array_key_exists($d['skillset_id'], $skillsets) )
			$skillsets[$d['skillset_id']] = 0;
		$skillsets[$d['skillset_id']]++;
	}
	if( $d['type'] == 'CC' )
		$cc[] = $d;
	else
		$hs[] = $d;
	
	$page_title = $d['school_name'] . ' - Plan of Study - ' . $d['view_name'];
	if($SITE->hasFeature('oregon_skillset')){
		$main_skillset = $d['view_skillset'];
	}

}

if( Request('format') == 'html' )
{
    if(count($drawings) == 0){
		drawing_not_found('postview', Request('id'));
    }
    if ($SITE->hasFeature('post_assurances')){
        $view = $DB->SingleQuery('SELECT published FROM vpost_views WHERE id='.Request('id'));
        $userId = $_SESSION['user_id'];
        if($userId==-1 && !$view['published']){
            header('Status: 404 Not Found');?>
            <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
            <html>
            <head>
            	<title><?= $page_title ?></title>
            </head>
            <body>
            	<h1>This view is unavailable.</h1>
            </body>
            </html>
            <?php
            die();
        }
    }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title><?= $page_title ?></title>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
	<link rel="stylesheet" href="/files/js/jquery/ui.tabs.css" />
	<link rel="stylesheet" href="/files/js/jquery/ui.all.css" />
	<link rel="stylesheet" href="/c/pstyle.css" />
	<link rel="stylesheet" href="/c/pstyle-print.css"<?=(array_key_exists('print', $_GET) ? '' : ' media="print"')?> />
	<?php if(defined('SITE_TEMPLATE') && file_exists(SITE_TEMPLATE . 'styles-header.css')): ?>
	    <link rel="stylesheet" href="/site-template/styles-header.css" />
	<?php endif; ?>
<?php
	if(!array_key_exists('print', $_GET))
	{
?>
	<script type="text/javascript">
		$(document).ready(function(){
			var $tabs = $("#tabshs");
			$tabs.tabs({
			  	create: function(event, ui) {
				    // Adjust hashes to not affect URL when clicked.
				    var widget = $tabs.data("uiTabs");
				    widget.panels.each(function(i){
				        this.id = "uiTab_" + this.id; // Prepend a custom string to tab id.
				        widget.anchors[i].hash = "#" + this.id;
				        $(widget.tabs[i]).attr("aria-controls", this.id);
				    });
				},
			    activate: function(event, ui) {
			        // Add the original "clean" tab id to the URL hash.
			        window.location.hash = ui.newPanel.attr("id").replace("uiTab_", "");
			    },
			});
			var $tabs = $("#tabscc");
			$tabs.tabs({
			  	create: function(event, ui) {
				    // Adjust hashes to not affect URL when clicked.
				    var widget = $tabs.data("uiTabs");
				    widget.panels.each(function(i){
				        this.id = "uiTab_" + this.id; // Prepend a custom string to tab id.
				        widget.anchors[i].hash = "#" + this.id;
				        $(widget.tabs[i]).attr("aria-controls", this.id);
				    });
				},
			    activate: function(event, ui) {
			        // Add the original "clean" tab id to the URL hash.
			        window.location.hash = ui.newPanel.attr("id").replace("uiTab_", "");
			    },
			});
			
		});
	</script>
<?php
	}
?>
</head>
<body>

<?php

echo '<div style="margin-bottom: 10px">';
echo '<div id="post_title">';
	echo ShowViewHeader(intval(Request('id')));
echo '</div>';
if($SITE->hasFeature('oregon_skillset')){
if( $main_skillset )
       {
               echo '<div id="skillset">';
                       echo $main_skillset;
               echo '</div>';
       }
}
/*
if( count($skillsets) > 0 )
{
	asort($skillsets);
	$skillsets = array_flip($skillsets);
	$skillset = $DB->SingleQuery('SELECT title FROM oregon_skillsets WHERE id = '.array_pop($skillsets));

	echo '<div id="skillset">';
		echo l('skillset name') . ': ' . $skillset['title'];
	echo '</div>';
}
*/
echo '</div>';

foreach( array('hs'=>$hs, 'cc'=>$cc) as $type=>$ds )
{
	echo '<div style="margin-bottom:10px;">';
	if( count($ds) == 0 )
	{
		
	}
	elseif( count($ds) == 1 )
	{
		try
		{
			$p = POSTChart::create($ds[0]['version_id']);
			$p->display();
		}
		catch( Exception $e )
		{
			echo '<div class="error">Drawing not found</div>';
		}
	}
	else
	{
	?>
	<div id="tabs<?=$type?>">
		<ul>
			<?php
			foreach( $ds as $i=>$d )
			{
				$school_name = str_replace(array(' High School', ' Community College'), '', $d['school_name']);
				echo '<li><a href="#tabs'.$type.'-'.($d['id']).'">' . $d['tab_name'] . '</a></li>';
			}
			?>
		</ul>
		<?php
		foreach( $ds as $i=>$d )
		{
			echo '<div id="tabs'.$type.'-'.($d['id']).'" class="tabs_'.$type.'">';
			try
			{
				$p = POSTChart::create($d['version_id']);
				$p->display($d['tab_name']);
			}
			catch( Exception $e )
			{
				echo '<div class="error">Drawing not found</div>';
			}
			echo '</div>';
		}
		?>

	</div>
	<?php
	}
	echo '</div>';
}

if(array_key_exists('print', $_GET)) {
	echo '<div class="footnote">Printed on ' . date('n/j/Y g:ia') . '</div>';
}

include('view/course_description_include.php');
?>
<script type="text/javascript">
	$(function(){
		$("#tabshs").bind("tabsshow", function(e, ui){
			$(ui.panel).find(".post_cell .cell_container").each(function(){
				if($(this).find("img").length > 0) {
					var h = $(this).parent(".post_cell").height();
					$(this).css({
						height: h + "px"
					});
				}
			});
		});
	});
</script>

<?php
if(isset($SITE) && method_exists($SITE, 'google_analytics')){
	echo $SITE->google_analytics(l('google analytics drawings'));
}
?>

</body>
</html>
<?php


}
elseif( Request('format') == 'js' )
{
		header("Content-type: text/javascript");
?>
		//This must remain at the top before other scripts are added.
		var scripts = document.getElementsByTagName('script');
		var tabId = scripts[scripts.length - 1].src.split('#')[1];

		var s=document.createElement('script');
		s.setAttribute('src','<?= getBaseUrl() ?>/c/log/post/<?=$_REQUEST['id']?>?url='+window.location);
		document.getElementsByTagName('body')[0].appendChild(s);

		var pc = document.getElementById("<?=(Request('container')?Request('container'):'postContainer')?>");

        //from MS site on how to detect IE versions
        var rv = -1; // Return value assumes failure.
        if (navigator.appName == 'Microsoft Internet Explorer') {
            var ua = navigator.userAgent;
            var re  = new RegExp("MSIE ([0-9]{1,}[\.0-9]{0,})");
            if (re.exec(ua) != null)
                rv = parseFloat( RegExp.$1 );
        }  else if (navigator.appName == 'Netscape') {
            var ua = navigator.userAgent;
            var re  = new RegExp("Trident/.*rv:([0-9]{1,}[\.0-9]{0,})");
            if (re.exec(ua) != null)
              rv = parseFloat( RegExp.$1 );
        }

		var iFrameSrc = "<?= getBaseUrl() ?>/c/study/<?=$_REQUEST['id']?>/embed.html";
        if(tabId){ iFrameSrc += '#' + tabId; }

        if( rv < 9.0 && typeof VBArray != "undefined" ) {  //all IE < 9
            var fr = document.createElement('<iframe src="'+iFrameSrc+'" width="'+pc.style.width+'" height="'+pc.style.height+'" frameborder="0" scrolling="no"></iframe>');
		} else {
			var fr = document.createElement('iframe');
			fr.setAttribute("width", pc.style.width);
			fr.setAttribute("height", pc.style.height);
			fr.setAttribute("src", iFrameSrc);
			fr.setAttribute("frameborder", "0");
			fr.setAttribute("scrolling", "auto");
            fr.setAttribute("onload", "iframeLoaded(fr)");
		}

		function iframeLoaded(fr) {
            if(fr) {
                var contentHeight = fr.contentWindow.document.body.scrollHeight; //add a small amount to compensate for scrollbar
                document.getElementById('postContainer').setAttribute("height", contentHeight);
                fr.height = "";
                fr.height = fr.contentWindow.document.body.scrollHeight;
            }
        }
		document.getElementById('postContainer').appendChild(fr);
<?php

}

?>
