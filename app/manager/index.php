<?php
session_start();

$_SESSION['managerid'] = "";
$_SESSION['name'] = "";
$_SESSION['function'] = NULL;

$host = "boarddata.cchpc7kznfed.ap-northeast-1.rds.amazonaws.com";
$user = "root";
$password = "password";
$database = "boarddata";

$mysqli = new mysqli($host, $user, $password, $database);
if($mysqli->connect_errno)
{
	echo "DB接続失敗". $mysqli->connect_error;
}			

$managerid = $_POST['managerid'];
$pass = $_POST['password'];
$security = $managerid.$pass;

$data = $mysqli->query("SELECT * FROM managers WHERE manager = '$managerid'");
if(!$data)
{
	echo "データなし";
}

if($managerid AND $pass)
{
	$row = mysqli_fetch_array($data, MYSQLI_ASSOC);

	if(password_verify($security, $row['pass']))
	{
		$_SESSION['managerid'] = $row['managerid'];
		$_SESSION['name'] = $row['name'];

		header('Location: ./managers.php');
		exit;
	}
	else
	{
		$msg = 'マネージャーIDもしくはパスワードが間違っています。';
	}
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>掲示板管理</title>
	<link href="css/style.css" rel="stylesheet">
</head>
<body bgcolor="black" text="white">
	<center>
	<div class="middle">
	<h1 class="title">掲示板管理ログイン</h1>
	<br>
	<form action="index.php" method="post">
		<h2>管理者ID</h2>
		<input name="managerid" class="managerids" value="<?php echo $managerid; ?>" required>

		<h2>パスワード</h2>
		<input name="password" type="password" value="<?php echo $pass;?>" class="passwords" required><br><br>

		<button class="button2" type="submit">ログイン</button><br>
		<p style="color: red"><?php echo $msg; ?></p>
	</form>
	</div>
	</center>
</body>
</html>