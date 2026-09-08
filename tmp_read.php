<?php
$f = file('resources/views/layouts/app.blade.php');
for ($i = 845; $i < 1070; $i++) {
    echo ($i+1) . '|' . $f[$i];
}




