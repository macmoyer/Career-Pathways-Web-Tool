<?php
global $DB, $drawing_id;
$drawing = $DB->SingleQuery("SELECT * FROM post_drawings WHERE id=".intval($_REQUEST['version_id']));
$drawing_main = $DB->SingleQuery("SELECT * FROM post_drawing_main WHERE id=".$drawing['parent_id']);
?>
<div id="toolbar">
	<div id="toolbar_header"></div>
	<div id="toolbar_content">
		<div style="margin-bottom:4px">
			<img src="/common/silk/lock<?= ($drawing['frozen']?'':'_open') ?>.png" width="16" height="16" id="lock_icon" />
			<div id="drawing_unlocked_msg" style="display: <?= $drawing['frozen']?'none':'inline' ?>">
				<a href="javascript:lock_drawing(<?= $drawing['version_num'] ?>)">lock</a> <span style="color:#555555">This version is currently editable. Click "lock" to prevent further edits.</span>
			</div>
			<div id="drawing_locked_msg" style="display:<?= $drawing['frozen']?'inline':'none' ?>">
				<span style="color:#555555">This version is locked. Copy it to a new version to make changes.</span>
			</div>
		</div>
	
		<?php if ($drawing['published'] == 0) : ?>
			<form action="/a/post_drawings.php" method="post" id="publishForm">
				<input type="hidden" name="drawing_id" value="<?=$drawing['id']?>" />
				<input type="hidden" name="action" value="publish" />
				<input type="submit" value="Publish" style="display:none" />
			</form>
			<a href="javascript:$('#publishForm').submit();" id="publishLink" class="toolbarButton">publish this version</a>
		<?php endif; ?>
		<a href="javascript:copyPopup('post', <?= $_REQUEST['version_id'] ?>)" class="toolbarButton">copy this version</a>
		<a href="/c/post/<?= $drawing_main['code'] . '/' . $drawing['version_num'] ?>.html?action=print" class="toolbarButton" target="_new">print this version</a>

		<script type="text/javascript">
			function lock_drawing(version) {
				ajaxCallback(function() {
						getLayer('lock_icon').src = '/common/silk/lock.png';
						getLayer('drawing_locked_msg').style.display = 'inline';
						getLayer('drawing_unlocked_msg').style.display = 'none';
					}, '/a/drawings_post.php?mode=post&action=lock&drawing_id=<?= $drawing['id'] ?>');
			}
		</script>
	</div>
</div>