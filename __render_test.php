<?php
session_start();
$_SESSION['loggedin']=true;
ob_start();
include 'Room_Network2.php';
$out = ob_get_clean();
file_put_contents('rendered_room_network2.html', $out);
echo 'rendered';
?>
