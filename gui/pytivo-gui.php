<?php
/*
	pytivo-gui.php

	WebGUI wrapper for the NAS4Free/XigmaNAS "PyTivo" add-on created by JoseMR.
	(https://www.xigmanas.com/forums)

	Copyright (c) 2016 Andreas Schmidhuber
	All rights reserved.

	Portions of NAS4Free (http://www.nas4free.org).
	Copyright (c) 2012-2016 The NAS4Free Project <info@nas4free.org>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice, this
	   list of conditions and the following disclaimer.
	2. Redistributions in binary form must reproduce the above copyright notice,
	   this list of conditions and the following disclaimer in the documentation
	   and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
	ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
	ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
	SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

	The views and conclusions contained in the software and documentation are those
	of the authors and should not be interpreted as representing official policies,
	either expressed or implied, of the NAS4Free Project.
*/
require("auth.inc");
require("guiconfig.inc");

$application = "PyTivo";
$prdname = "pytivo";
$pgtitle = array(gtext("Extensions"), "PyTivo");

// For NAS4Free 10.x versions.
$return_val = mwexec("/bin/cat /etc/prd.version | cut -d'.' -f1 | /usr/bin/grep '10'", true);
if ($return_val == 0) {
	if (is_array($config['rc']['postinit'] ) && is_array( $config['rc']['postinit']['cmd'] ) ) {
		for ($i = 0; $i < count($config['rc']['postinit']['cmd']);) { if (preg_match('/pytivoinit/', $config['rc']['postinit']['cmd'][$i])) break; ++$i; }
	}
}

// Initialize some variables.
//$rootfolder = dirname($config['rc']['postinit']['cmd'][$i]);
$pidfile = $prdname;
$confdir = "/var/etc/pytivoconf";
$cwdir = exec("/usr/bin/grep 'INSTALL_DIR=' {$confdir}/conf/pytivo_config | cut -d'\"' -f2");
$rootfolder = $cwdir;
$configfile = "{$rootfolder}/conf/pytivo_config";
$versionfile = "{$rootfolder}/version";
$pytivoversion = "{$rootfolder}/pytivoversion";
//$date = strftime('%c');                // Previous PHP versions, deprecated as of PHP 8.1.
$date = date('D M d h:i:s Y', time());   // Equivalent date replacement for the previous strftime function.
$logfile = "{$rootfolder}/log/pytivo_ext.log";
$logevent = "{$rootfolder}/log/pytivo_last_event.log";

if ($rootfolder == "") $input_errors[] = gtext("Extension installed with fault");
else {
// Initialize locales.
	$textdomain = "/usr/local/share/locale";
	$textdomain_pytivo = "/usr/local/share/locale-pytivo";
	if (!is_link($textdomain_pytivo)) { mwexec("ln -s {$rootfolder}/locale-pytivo {$textdomain_pytivo}", true); }
	bindtextdomain("xigmanas", $textdomain_pytivo);
}
if (is_file("{$rootfolder}/postinit")) unlink("{$rootfolder}/postinit");

// Retrieve IP@.
$ipaddr = get_ipaddr($config['interfaces']['lan']['if']);
$url = htmlspecialchars("http://{$ipaddr}:9032");
$ipurl = "<a href='{$url}' target='_blank'>{$url}</a>";

if ($_POST) {
	if (isset($_POST['start']) && $_POST['start']) {
		$return_val = mwexec("nohup {$rootfolder}/pytivoinit -s >/dev/null 2>&1 &", true);
		if ($return_val == 0) {
			$savemsg .= gtext("PyTivo started successfully.");
			exec("echo '{$date}: {$application} successfully started' >> {$logfile}");
		}
		else {
			$input_errors[] = gtext("PyTivo startup failed.");
			exec("echo '{$date}: {$application} startup failed' >> {$logfile}");
		}
	}

	if (isset($_POST['stop']) && $_POST['stop']) {
		$return_val = mwexec("{$rootfolder}/pytivoinit -p; exit 0", true);
		if ($return_val == 0) {
			$savemsg .= gtext("PyTivo stopped successfully.");
			exec("echo '{$date}: {$application} successfully stopped' >> {$logfile}");
		}
		else {
			$input_errors[] = gtext("PyTivo stop failed.");
			exec("echo '{$date}: {$application} stop failed' >> {$logfile}");
		}
	}

	if (isset($_POST['restart']) && $_POST['restart']) {
		$return_val = mwexec("nohup {$rootfolder}/pytivoinit -r >/dev/null 2>&1 &", true);
		if ($return_val == 0) {
			$savemsg .= gtext("PyTivo restarted successfully.");
			exec("echo '{$date}: {$application} successfully restarted' >> {$logfile}");
		}
		else {
			$input_errors[] = gtext("PyTivo restart failed.");
			exec("echo '{$date}: {$application} restart failed' >> {$logfile}");
		}
	}

	if(isset($_POST['upgrade']) && $_POST['upgrade']):
		$cmd = sprintf('%1$s/pytivoinit -u > %2$s',$rootfolder,$logevent);
		$return_val = 0;
		$output = [];
		exec($cmd,$output,$return_val);
		if($return_val == 0):
			ob_start();
			include("{$logevent}");
			$ausgabe = ob_get_contents();
			ob_end_clean(); 
			$savemsg .= str_replace("\n", "<br />", $ausgabe)."<br />";
		else:
			$input_errors[] = gtext('An error has occurred during upgrade process.');
			$cmd = sprintf('echo %s: %s An error has occurred during upgrade process. >> %s',$date,$application,$logfile);
			exec($cmd);
		endif;
	endif;

	// Remove only extension related files during cleanup.
	if (isset($_POST['uninstall']) && $_POST['uninstall']) {
		bindtextdomain("xigmanas", $textdomain);
		if (is_link($textdomain_pytivo)) mwexec("rm -f {$textdomain_pytivo}", true);
		if (is_dir($confdir)) mwexec("rm -rf {$confdir}", true);
		mwexec("rm /usr/local/www/pytivo-gui.php && rm -R /usr/local/www/ext/pytivo-gui", true);
		mwexec("{$rootfolder}/pytivoinit -t", true);
		mwexec("{$rootfolder}/pytivoinit -p", true);
		$uninstall_cmd = "echo 'y' | {$rootfolder}/pytivoinit -d";
		mwexec($uninstall_cmd, true);

		// Remove postinit cmd in NAS4Free 10.x versions.
		$return_val = mwexec("/bin/cat /etc/prd.version | cut -d'.' -f1 | /usr/bin/grep '10'", true);
			if ($return_val == 0) {
				if (is_array($config['rc']['postinit']) && is_array($config['rc']['postinit']['cmd'])) {
					for ($i = 0; $i < count($config['rc']['postinit']['cmd']);) {
					if (preg_match('/pytivoinit/', $config['rc']['postinit']['cmd'][$i])) { unset($config['rc']['postinit']['cmd'][$i]); }
					++$i;
				}
			}
			write_config();
		}

		// Remove postinit cmd in NAS4Free later versions.
		if (is_array($config['rc']) && is_array($config['rc']['param'])) {
			$postinit_cmd = "{$rootfolder}/pytivoinit";
			$value = $postinit_cmd;
			$sphere_array = &$config['rc']['param'];
			$updateconfigfile = false;
		if (false !== ($index = array_search_ex($value, $sphere_array, 'value'))) {
			unset($sphere_array[$index]);
			$updateconfigfile = true;
		}
		if ($updateconfigfile) {
			write_config();
			$updateconfigfile = false;
		}
	}
	header("Location:index.php");
}

	if (isset($_POST['save']) && $_POST['save']) {
		// Ensure to have NO whitespace & trailing slash.
		//$backup_path = rtrim(trim($_POST['backup_path']),'/');
		//if ("{$backup_path}" == "") $backup_path = "{$rootfolder}/backup";
		//	else exec("/usr/sbin/sysrc -f {$configfile} BACKUP_DIR={$backup_path}");
		if (isset($_POST['enable'])) { 
			exec("/usr/sbin/sysrc -f {$configfile} PYTIVO_ENABLE=YES");
			mwexec("nohup {$rootfolder}/pytivoinit >/dev/null 2>&1 &", true);
			exec("echo '{$date}: Extension settings saved and enabled' >> {$logfile}");
		}
		else {
			exec("/usr/sbin/sysrc -f {$configfile} PYTIVO_ENABLE=NO");
			$return_val = mwexec("{$rootfolder}/pytivoinit -p, exit 0", true);
			if ($return_val == 0) {
				$savemsg .= gtext("PyTivo stopped successfully.");
				exec("echo '{$date}: Extension settings saved and disabled' >> {$logfile}");
			}
			else {
				$input_errors[] = gtext("PyTivo stop failed.");
				exec("echo '{$date}: {$application} stop failed' >> {$logfile}");
			}
		}
	}
}

// Update some variables.
$pytivoenable = exec("/bin/cat {$configfile} | /usr/bin/grep 'PYTIVO_ENABLE=' | cut -d'\"' -f2");
//$backup_path = exec("/bin/cat {$configfile} | /usr/bin/grep 'BACKUP_DIR=' | cut -d'\"' -f2");

function get_version_pytivo() {
	global $pytivoversion;
	if (is_file("{$pytivoversion}")) {
		exec("/bin/cat {$pytivoversion}", $result);
		return ($result[0]);
	}
	else {
		exec("/usr/local/sbin/pkg info -I {$prdname}", $result);
		return ($result[0]);
	}
}

function get_version_ext() {
	global $versionfile;
	exec("/bin/cat {$versionfile}", $result);
	return ($result[0]);
}

function get_process_info() {
	global $pidfile;
	if (exec("pgrep -f {$pidfile}")) { $state = '<a style=" background-color: #00ff00; ">&nbsp;&nbsp;<b>'.gtext("running").'</b>&nbsp;&nbsp;</a>'; }
	else { $state = '<a style=" background-color: #ff0000; ">&nbsp;&nbsp;<b>'.gtext("stopped").'</b>&nbsp;&nbsp;</a>'; }
	return ($state);
}

function get_process_pid() {
	global $pidfile;
	exec("pgrep -f {$pidfile}", $state); 
	return ($state[0]);
}

if (is_ajax()) {
	$getinfo['info'] = get_process_info();
	$getinfo['pid'] = get_process_pid();
	$getinfo['pytivo'] = get_version_pytivo();
	$getinfo['ext'] = get_version_ext();
	render_ajax($getinfo);
}

bindtextdomain("xigmanas", $textdomain);
include("fbegin.inc");
bindtextdomain("xigmanas", $textdomain_pytivo);
?>
<script type="text/javascript">//<![CDATA[
$(document).ready(function(){
	var gui = new GUI;
	gui.recall(0, 2000, 'pytivo-gui.php', null, function(data) {
		$('#getinfo').html(data.info);
		$('#getinfo_pid').html(data.pid);
		$('#getinfo_pytivo').html(data.pytivo);
		$('#getinfo_ext').html(data.ext);
	});
});
//]]>
</script>
<!-- The Spinner Elements -->
<script src="js/spin.min.js"></script>
<!-- use: onsubmit="spinner()" within the form tag -->
<script type="text/javascript">
<!--
function enable_change(enable_change) {
	var endis = !(document.iform.enable.checked || enable_change);
	document.iform.start.disabled = endis;
	document.iform.stop.disabled = endis;
	document.iform.restart.disabled = endis;
	document.iform.upgrade.disabled = endis;
}
//-->
</script>
<form action="pytivo-gui.php" method="post" name="iform" id="iform" onsubmit="spinner()">
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr><td class="tabcont">
			<?php if (!empty($input_errors)) print_input_errors($input_errors);?>
			<?php if (!empty($savemsg)) print_info_box($savemsg);?>
			<table width="100%" border="0" cellpadding="6" cellspacing="0">
				<?php html_titleline_checkbox("enable", gtext("PyTivo"), $pytivoenable == "YES", gtext("Enable"));?>
				<?php html_text("installation_directory", gtext("Installation directory"), sprintf(gtext("The extension is installed in %s"), $rootfolder));?>
				<tr>
					<td class="vncellt"><?=gtext("PyTivo version");?></td>
					<td class="vtable"><span name="getinfo_pytivo" id="getinfo_pytivo"><?=get_version_pytivo()?></span></td>
				</tr>
				<tr>
					<td class="vncellt"><?=gtext("Extension version");?></td>
					<td class="vtable"><span name="getinfo_ext" id="getinfo_ext"><?=get_version_ext()?></span></td>
				</tr>
				<tr>
					<td class="vncellt"><?=gtext("Status");?></td>
					<td class="vtable"><span name="getinfo" id="getinfo"><?=get_process_info()?></span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;PID:&nbsp;<span name="getinfo_pid" id="getinfo_pid"><?=get_process_pid()?></span></td>
				</tr>
				<?php html_text("url", gtext("WebGUI")." ".gtext("URL"), $ipurl);?>
			</table>
			<div id="submit">
				<input id="save" name="save" type="submit" class="formbtn" title="<?=gtext("Save settings");?>" value="<?=gtext("Save");?>"/>
				<input name="start" type="submit" class="formbtn" title="<?=gtext("Start PyTivo");?>" value="<?=gtext("Start");?>" />
				<input name="stop" type="submit" class="formbtn" title="<?=gtext("Stop PyTivo");?>" value="<?=gtext("Stop");?>" />
				<input name="restart" type="submit" class="formbtn" title="<?=gtext("Restart PyTivo");?>" value="<?=gtext("Restart");?>" />
				<input name="upgrade" type="submit" class="formbtn" title="<?=gtext("Upgrade Extension and PyTivo Packages");?>" value="<?=gtext("Upgrade");?>" />
			</div>
			<div id="remarks">
				<?php html_remark("note", gtext("Info"), sprintf(gtext("For general information visit the following link(s):")));?>
				<div id="enumeration"><ul><li><a href="https://pytivo.sourceforge.io/wiki/index.php/PyTivo" target="_blank" > About PyTivo</a></li></ul></div>
			</div>
			<table width="100%" border="0" cellpadding="6" cellspacing="0">
				<?php html_separator();?>
				<?php html_titleline(gtext("Uninstall"));?>
				<?php html_separator();?>
			</table>
			<div id="submit1">
				<input name="uninstall" type="submit" class="formbtn" title="<?=gtext("Uninstall Extension and PyTivo completely");?>" value="<?=gtext("Uninstall");?>" onclick="return confirm('<?=gtext("PyTivo Extension and PyTivo packages will be completely removed, ready to proceed?");?>')" />
			</div>
		</td></tr>
	</table>
	<?php include("formend.inc");?>
</form>
<script type="text/javascript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc");?>
