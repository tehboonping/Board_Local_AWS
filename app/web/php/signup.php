<?php
session_start();

if($_SESSION['enable'])
{
	header('Location: ../index.php');
	exit;
}

$host = "boarddata.cchpc7kznfed.ap-northeast-1.rds.amazonaws.com";
$user = "root";
$password = "password";
$database = "boarddata";

$mysqli = new mysqli($host, $user, $password, $database);
if($mysqli->connect_errno)
{
	echo "DB接続失敗". $mysqli->connect_error;
}			

$userid = $_POST["accid"];
$pass = $_POST['accpass'];
$name = $_POST['accname'];
$security = $userid.$pass;
$passsave = password_hash($security, PASSWORD_DEFAULT);

$data = $mysqli->query("SELECT * FROM accounts WHERE user = '$userid'");
$samename = $mysqli->query("SELECT * FROM accounts WHERE username = '$name'");

if(!$data)
{
	echo "dataデータなし";
}
if(!$samename)
{
	echo "samenameデータなし";
}

$row = mysqli_fetch_array($data, MYSQLI_ASSOC);
$rowname = mysqli_fetch_array($samename, MYSQLI_ASSOC);

if($userid AND $pass And $name)
{
	if($userid === $row['user'])
	{
		$msg = '既存のユーザーIDが存在します。別のIDを入力してください。';
		$color = 'red';
	}
	else if($name === $rowname['username'])
	{
		$msg = '既存の名前が存在します。別の名前を入力してください。';
		$color = 'red';
	}
	else
	{
		$db = $mysqli->query("INSERT INTO accounts(user,pass,username,lv) VALUES('$userid','$passsave','$name','2')");
		$msg ='アカウント申請完了！ログイン画面に戻って、ログインして見ましょう。';
		$color = 'green';

		if(!$db){ echo "保存失敗"; }

		$userid = "";
		$pass = "";
		$name = "";
	}
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>掲示板アカウント申請</title>
	<link href="style.css" rel="stylesheet">
</head>
<body>
	<center>
	<h1 class="title">掲示板アカウント申請</h1>
	<br>
	<form action="signup.php" method="post">
		<h2>ユーザーID</h2>
		<input name="accid" class="userids" value="<?php echo $userid; ?>" required>

		<h2>パスワード</h2>
		<input name="accpass" type="password" class="passwords" value="<?php echo $pass; ?>" required>

		<h2>名前</h2>
		<input name="accname" class="userids" value="<?php echo $name; ?>" required>
		<br><br>
		<button class="button1" type="submit">申請</button><br>
		<p style="color: <?php echo $color; ?>"><?php echo $msg; ?></p>
	</form>
	<br><hr style="height: 2px; background-color: black;"><br>
	<button class="button1" onclick="location.href='../index.php'">ログイン画面に戻る</button>
</body>
</html>