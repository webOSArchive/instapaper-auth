<?php
session_start();
$now = time();
if (isset($_SESSION['discard_after']) && $now > $_SESSION['discard_after']) {
    session_unset();
    session_destroy();
    session_start();
}
// either new or old, it should live at most for 30 minutes
$_SESSION['discard_after'] = $now + 1800;

if (!isset($_SESSION["attempts"])) {
    $_SESSION["attempts"] = 0;
}
$docRoot="../";
include ($docRoot . "config.php");
include ($docRoot . "common.php");
echo file_get_contents("https://www.webosarchive.org/app-template/header.php?docRoot=" . urlencode($docRoot) . "&appTitle=" . urlencode($appTitle) . "&protocol=" . findProtocol());
?>
<?php
if ($_SESSION["attempts"] <= $maxAttempts) {
?>
<script>
window.addEventListener('load', function() {
    document.getElementById("activationCode").focus();
});
</script>
<div align="center">
    <?php
    //Show appropriate instructions for platform
    $client = strtolower($_SERVER['HTTP_USER_AGENT']);
    if (strpos($client, "hpwos") || strpos($client, "webos")) {
        echo "Welcome webOS User! Unfortunately, you cannot complete Instapaper authorization on your device.<br>Please visit this site from a modern browser!";
    } else {
    ?>
    <form action="../instapaper-auth-1.php" method="POST">
        <table cellpadding="6" cellspacing="0" border="0">
        <tr><td colspan="2"><b>Enter the activation code shown on your webOS device:</b></td></tr>
        <tr>
            <td align="right">Code:</td>
            <td><input type="text" name="activationCode" id="activationCode" style="text-align: center;font-size: larger;text-transform: uppercase;" autocomplete="off"></td>
        </tr>
        <tr><td colspan="2"><br><b>Enter your Instapaper login:</b></td></tr>
        <tr>
            <td align="right">Username:</td>
            <td><input type="text" name="username" id="username" style="font-size: medium;" autocomplete="username"></td>
        </tr>
        <tr>
            <td align="right">Password:</td>
            <td><input type="password" name="password" id="password" style="font-size: medium;" autocomplete="current-password"></td>
        </tr>
        <tr>
            <td></td>
            <td><br><input type="submit" name="btnInstapaperAuth" id="btnInstapaperAuth" value="Sign in to Instapaper" style="font-size: medium;"></td>
        </tr>
        </table>
    </form>
</div>
<?php
    }
} else {
    echo "<p align='middle'>Too many attempts (" . $_SESSION["attempts"] . "/" . $maxAttempts . ") Try again later!</p>";
}
?>
