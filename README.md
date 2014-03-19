# Git deploy script

This script automatically deploys web sites or code using Git. To be used as
post-commit hook.

Inspired by [markomarkovic/simple-php-git-deploy](https://github.com/markomarkovic/simple-php-git-deploy) which has many more features, but is more complicated to use.

## Requirements

* `git` and `rsync` are required on the server that's running the script
* The system user running PHP (e.g. `www-data`) needs to have the necessary access permissions for the `TMP_DIR` and `TARGET_DIR` locations on the server.
* If the Git repo you wish to deploy is private, the system user running PHP also needs to have the right SSH keys to access the remote repository.

## Usage

 * Configure the script and put it on a web server.
 * Configure your Git repository to call this script when the code is updated using a post-commit hook.
