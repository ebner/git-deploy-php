<?php
/**
 * Git deploy script
 *
 * Author: Hannes Ebner <hannes@ebner.se>, 2014
 *
 * Clones a Git repository to deploy it to a local path. Subsequent calls may
 * fetch instead of clone, provided the temporary directory is not configured
 * to be cleared.
 *
 * Inspired by https://github.com/markomarkovic/simple-php-git-deploy/.
 *
 * Possible sources of failure:
 *
 * - The public key of www-data has to have read access to the repository.
 * - The connection will fail if the SSH server is not trusted, to avoid this the
 *   server should be access from the command line at least once to get its
 *   fingerprint into the local SSH configuration.
 *
 * License
 *
 * Hannes Ebner licenses this work under the terms of the Apache License 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. See the LICENSE file distributed with this work for the full License.
 */

//// Main settings

// This value goes into the "token"-URL parameter. Recommended to use "uuid" for generating a token.
define('ACCESS_TOKEN', '8184a498-afaf-11e3-8d19-3c970e88a290');

// The repository to clone
define('REMOTE_REPOSITORY', 'git@github.com:org/repo.git');

// The branch to be deployed
define('BRANCH', 'master');

// The location to deploy to. Trailing slash is required
define('TARGET_DIR', '/var/www/site/');

//// Optional settings

// Whether to delete the files that are not in the repository but are on the local (server) machine.
define('DELETE_FILES', false);

// The directories and files that are to be excluded. Rsync exclude pattern syntax for each element.
define('EXCLUDE', serialize(array('.git', '.gitignore')));

// A temporary directory, the default setting probably works
define('TMP_DIR', '/tmp/gds-'.md5(REMOTE_REPOSITORY).'-'.BRANCH.'/');

// Whether to remove the TMP_DIR after the deployment
define('CLEAN_UP', true);

// Output the version of the deployed code
define('VERSION_FILE', TMP_DIR.'VERSION.txt');

// Time limit for each command
define('TIME_LIMIT', 30);

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Git deploy script</title>
</head>
<body>
<?php
if (!isset($_GET['token']) || $_GET['token'] !== ACCESS_TOKEN) {
	die('Access denied');
}

if (isset($_GET['async'])) {
        ob_end_clean();
        header("Connection: close");
        ignore_user_abort(true);
        ob_start();
        $size = ob_get_length();
        header("Content-Length: $size");
        http_response_code(202);
        ob_end_flush();
        flush();
}
?>
<pre>

Deploying <?php echo REMOTE_REPOSITORY; ?> <?php echo BRANCH."\n"; ?>
to        <?php echo TARGET_DIR; ?> ...

<?php
$commands = array();

// ========================================[ Pre-Deployment steps ]===

if (!is_dir(TMP_DIR)) {
	// Clone the repository into the TMP_DIR
	$commands[] = sprintf('git clone --depth=1 --branch %s %s %s', BRANCH, REMOTE_REPOSITORY, TMP_DIR);
} else {
	// TMP_DIR exists and hopefully already contains the correct remote origin
	// so we'll fetch the changes and reset the contents.
	$commands[] = sprintf('git --git-dir="%s.git" --work-tree="%s" fetch origin %s', TMP_DIR, TMP_DIR, BRANCH);
	$commands[] = sprintf('git --git-dir="%s.git" --work-tree="%s" reset --hard FETCH_HEAD', TMP_DIR, TMP_DIR);
}

// Update the submodules
$commands[] = sprintf('git submodule update --init --recursive');

// Describe the deployed version
if (defined('VERSION_FILE') && VERSION_FILE !== '') {
	$commands[] = sprintf('git --git-dir="%s.git" --work-tree="%s" describe --always > %s',	TMP_DIR, TMP_DIR, VERSION_FILE);
}

// ==================================================[ Deployment ]===

// Compile exclude parameters
$exclude = '';
foreach (unserialize(EXCLUDE) as $exc) {
	$exclude .= ' --exclude=\''.$exc.'\'';
}
// Deployment command
$commands[] = sprintf('rsync -rltgoDzv %s %s %s %s', TMP_DIR, TARGET_DIR, (DELETE_FILES) ? '--delete-after' : '', $exclude);

// =======================================[ Post-Deployment steps ]===

// Remove the TMP_DIR (depends on CLEAN_UP)
if (CLEAN_UP) {
	$commands['cleanup'] = sprintf('rm -rf %s', TMP_DIR);
}

// =======================================[ Run the command steps ]===

foreach ($commands as $command) {
	set_time_limit(TIME_LIMIT); // Reset the time limit for each command
	if (file_exists(TMP_DIR) && is_dir(TMP_DIR)) {
		chdir(TMP_DIR); // Ensure that we're in the right directory
	}
	$tmp = array();
	exec($command.' 2>&1', $tmp, $return_code); // Execute the command
	// Output the result
	printf('$ %s<br/>%s<br/>', htmlentities(trim($command)), htmlentities(trim(implode("\n", $tmp))));
	flush();

	// Error handling and cleanup
	if ($return_code !== 0) {
		printf('Error encountered, stopping the script to prevent possible data loss.');
		if (CLEAN_UP) {
			$tmp = shell_exec($commands['cleanup']);
			printf('Cleaning up temporary files ...<br/>$ %s<br/>%s',
				htmlentities(trim($commands['cleanup'])),
				htmlentities(trim($tmp))
			);
		}
		error_log(sprintf('Deployment error! %s', __FILE__));
		break;
	}
}
?>

Done.
</pre>
</body>
</html>
