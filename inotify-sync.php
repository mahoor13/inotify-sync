#!/usr/bin/php
<?php

/*
** usage: inotify-sync.php <source path> <destination path>
** Any change on source folder effects on destination folder recursively
*/

$src = realpath($argv[1]) . '/';
$dst = realpath($argv[2]) . '/';
if (empty($src) || !isset($argv[1])) {
    die("Source path not found!\n");
}
if (empty($dst) || !isset($argv[1]) || $src == $dst) {
    die("Destination path not found!\n");
}
chdir($src);

// run process and listen to output stream
$fp = popen("inotifywait --format '%e::%w::%f' -m -r -e modify,create,delete,move --exclude vendor --exclude node_modules --exclude storage --exclude '^\.' '{$src}'", "r");
while(!feof($fp)) {
    // read upto 100k of data
    $msg = fread($fp, 100000);
    $msg = explode("\n", $msg);

    foreach ($msg as $row) {
        //echo $row, "\n";
        $row = explode('::', $row);
        // unhandled inotifywait output
        if (count($row) != 3) continue;
        list($event, $path, $file) = $row;
        $path = explode($src, $path, 2)[1];

        echo "\n{$event}:\t";
        $event = explode(',', strtolower($event), 2);
        $isDir = isset($event[1]) && $event[1] == 'isdir';
        $event = $event[0];
        if (empty($path)) continue;
        // action
        switch ($event) {
            case 'create':
            case 'modify':
                $cmd = "rsync -p -o -g -r -R '{$path}{$file}' '{$dst}'";
                echo $cmd;
                shell_exec($cmd);
                break;
            
            case 'delete':
                $cmd = "rm -rf '{$dst}{$path}{$file}'";
                echo $cmd;
                shell_exec($cmd);
                break;

            case 'moved_from':
                $moveFrom = $path . $file;
                break;

            case 'moved_to':
                $cmd = "mv '{$dst}{$moveFrom}' '{$dst}{$path}{$file}'";
                echo $cmd;
                shell_exec($cmd);
                break;            
        } 
    }
}
fclose($fp);
