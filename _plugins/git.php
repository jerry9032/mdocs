<?php
$_git_flock_fp = NULL;
function _git_create_edit_file($file, $method) {
    global $_git_flock_fp;
    $git = "~/.jumbo/bin/git";
    if (!is_dir('_doc/.git')) {
        return;
    }
    //file_put_contents('aaaaa', $file);
    $file = substr($file, 5);
    $retcode = 0;
    $output = array();
    $f = escapeshellarg($file);
    if (trim(shell_exec("cd _doc && $git status --porcelain $f | wc -l")) === "0" && $method == "modify") {
        return;
    }
    if (function_exists('_identify_get_user')) {
        $user = _identify_get_user();
        $git_comment = "$user $method $file";
    } else {
        $git_comment = "$method $file";
    }
    #exec("cd _doc \\
    #   && $git checkout -B tmpbranch \\
    #   && $git add $f \\
    #   && $git commit -m 'Modify file '$file \\
    #   && $git checkout master \\
    #   && $git fetch \\
    #   && $git reset --hard origin/master \\
    #   && $git checkout tmpbranch \\
    #   && $git rebase master \\
    #   && $git checkout master \\
    #   && $git merge tmpbranch \\
    #   && $git branch -d tmpbranch \\
    #   && $git push origin master
    #   ", $output, $retcode);
    exec("cd _doc \\
       && $git checkout master \\
       && $git pull origin master \\
       && $git add $f \\
       && $git commit -m '$git_comment' \\
       && $git push origin master
       ", $output, $retcode);
    if ($retcode != 0) {
        #exec("cd _doc && $git rebase --abort");
        #exec("cd _doc && $git checkout master && $git reset --hard");
        exec("cd _doc && $git checkout master");
        echo "Commit to git failed, maybe conflict\n";
        flock($_git_flock_fp, LOCK_UN);
        return ACTION_ABORT;
    }
    flock($_git_flock_fp, LOCK_UN);
}

function _git_after_save_edit($file) {
    _git_create_edit_file($file, "modify");
}

function _git_create($file) {
    _git_create_edit_file($file, "create");
}

function _git_before_save_edit() {
    global $_git_flock_fp;
    $git = "~/.jumbo/bin/git";
    if (!is_dir('_doc/.git')) {
        return;
    }
    $_git_flock_fp = fopen('_doc/.git/config', 'r');
    $ret = flock($_git_flock_fp, LOCK_EX);
    if (!$ret) {
        echo "Failed to acquire lock]\n";
        return ACTION_ABORT;
    }
}

function _git_sync() {
    $git = "~/.jumbo/bin/git";
    if (!is_dir('_doc/.git')) {
        return;
    }
    #exec("cd _doc && $git fetch && $git reset --hard origin/master");
    exec("cd _doc && $git fetch");
}

function _git_pull() {
    // webhook for gitlab
    $git = "~/.jumbo/bin/git";
    if (!is_dir('_doc/.git')) {
        return;
    }
    exec("cd _doc && $git pull");
}

add_action('after_save_edit', '_git_after_save_edit');
add_action('before_save_edit', '_git_before_save_edit');
add_action('before_view', '_git_sync');
add_action('before_edit', '_git_sync');
add_action('before_commit', '_git_sync');

register_special('git_pull', '_git_pull');
