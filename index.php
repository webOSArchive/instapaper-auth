<?php
$docRoot = "./";
include($docRoot . "config.php");
include($docRoot . "common.php");
echo file_get_contents("https://www.webosarchive.org/app-template/header.php?docRoot=" . $docRoot . "&appTitle=" . $appTitle . "&protocol=" . findProtocol());
?>
<p align="middle">Instapaper is available on webOS! You'll need an app for your device to get started.</p>
<p align="middle">Check out <a href="<?php echo findProtocol(); ?>://appcatalog.webosarchive.org/app/ReadOnTouchPRO3.0">ReadOnTouch Pro</a> in the App Museum!</p>
<br/>
<p align="middle">Got the app and ready to <a href="/activate">activate</a>?
<?php
include($docRoot . "inc/footer.php");
?>