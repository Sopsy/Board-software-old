<?php

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

include __DIR__ . '/../public/inc/engine.class.php';
new Engine(['db' => '', 'fileupload' => '']);

$q = $db->q("SELECT id, extension FROM file WHERE extension IN ('jpg', 'png') ORDER BY id DESC");

while ($file = $q->fetch_assoc()) {
    $fileName = str_pad(base_convert($file['id'], 10, 36), 5, '0', STR_PAD_LEFT);
    $folder = $fileName[0] . '/' . $fileName[1] . '/' . $fileName[2];

    $src = $engine->cfg->filesDir . '/' . $folder . '/' . $fileName . '.' . $file['extension'];
    $size_before = filesize($src);
    $file['id'] = (int)$file['id'];

    [$full_width_before, $full_height_before] = getimagesize($src);

    $tmp = '/tmp/' . uniqid('rescale', true);

    $newMaxWidth = 1280;
    $newMaxHeight = 1280;

    if ($full_width_before <= $newMaxWidth && $full_height_before <= $newMaxHeight) {
        echo 'Skipping: ' . $src . ' (' . $full_width_before . 'x' . $full_height_before . ")\n";
        continue;
    }

    $rescaled = $fileupload->createImage($src, $tmp, $newMaxWidth, $newMaxHeight);
    if (!$rescaled || !is_file($tmp) || filesize($tmp) === 0) {
        echo 'ERROR1: ' . $src . "\n";
        continue;
    }

    $fileupload->jpegtran($tmp, true);
    if (!is_file($tmp) || filesize($tmp) === 0) {
        echo 'ERROR2: ' . $src . "\n";
        continue;
    }

    unlink($src);
    $src = str_replace('.png', '.jpg', $src);
    rename($tmp, $src);

    $md5 = md5(file_get_contents($src));
    $db->q("INSERT INTO file_md5 (md5, file_id) VALUES (UNHEX('" . $md5 . "'), " . $file['id'] . ')');

    [$full_width, $full_height] = getimagesize($src);

    $filesize = filesize($src);
    $db->q("UPDATE file SET extension = 'jpg' WHERE id = " . $file['id'] . " LIMIT 1");

    echo $file['id'] . ': ' . $src . ' created: ' . round($size_before / 1024,
            2) . 'KB -> ' . round($filesize / 1024, 2) . 'KB (' . round((100 - ($filesize / $size_before) * 100),
            2) . "% reduction) \n";
}
