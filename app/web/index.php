<?php
session_start();

$host = "boarddata.cchpc7kznfed.ap-northeast-1.rds.amazonaws.com";
$user = "root";
$password = "password";
$database = "boarddata";

$mysqli = new mysqli($host, $user, $password, $database);
if($mysqli->connect_errno)
{
	echo "DB接続失敗". $mysqli->connect_error;
}

date_default_timezone_set("Asia/Tokyo");
$now = date("Y-m-d H:i:s");

$maintenance = $mysqli->query("SELECT * FROM maintenances WHERE starttime <= '$now' AND endtime >= '$now'");
if($maintenance)
{ 
	$mdata = mysqli_fetch_array($maintenance, MYSQLI_ASSOC);
	$enable = $mdata['enable'];
}
else
{
	$enable = false;
}

$_SESSION['enable'] = $enable;

if($enable)
{
	$starttime = $mdata['starttime'];
	$endtime = $mdata['endtime'];
	$comment = $mdata['comment'];
}
else
{
	$_SESSION['accountid'] = "";
	$_SESSION['username'] = "";
	$_SESSION['Developer'] = "";

	$userid = $_POST['userid'];
	$pass = $_POST['password'];
	$security = $userid.$pass;

	$redis = new Redis();
	$redis->connect($_ENV['REDIS_HOST'],6379);

	for($i = 1;$i <= $redis->dbsize();$i++)
	{
		$redisdata = $redis->hGetALL('accounts'.$i);

		if($redisdata && $redisdata['user'] === $userid)
		{
			if(password_verify($security, $redisdata['pass']))
			{
				$_SESSION['accountid'] = $redisdata['accountid'];
				$_SESSION['username'] = $redisdata['username'];
				$_SESSION['Developer'] = $redisdata['lv'];

				header('Location: ./php/users.php');
				exit;
			}
			else
			{
				$msg = 'ユーザーIDもしくはパスワードが間違っています。';
			}
			break;
		}
	}

	$data = $mysqli->query("SELECT * FROM accounts WHERE user = '$userid'");
	if(!$data)
	{
		echo "データなし";
	}

	if($userid AND $pass)
	{
		$row = mysqli_fetch_array($data, MYSQLI_ASSOC);

		if(password_verify($security, $row['pass']))
		{
			$_SESSION['accountid'] = $row['accountid'];
			$_SESSION['username'] = $row['username'];
			$_SESSION['Developer'] = $row['lv'];

			$cache = array(
				'accountid' => $row['accountid'],
				'user' => $row['user'],
				'pass' => $row['pass'],
				'username' => $row['username'],
				'lv' => $row['lv']
			);

			$count = 1;
			$bool = $redis->keys('accounts'.$count);
			while($bool)
			{
				$count++;
				$bool = $redis->keys('accounts'.$count);
			}

			$redis->hMSet('accounts'.$count, $cache);

			header('Location: ./php/users.php');
			exit;
		}
		else
		{
			$msg = 'ユーザーIDもしくはパスワードが間違っています。';
		}
	}
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>掲示板</title>
	<link href="php/style.css" rel="stylesheet">
</head>
<body>
	<?php if(!$enable) { ?>
	<center>
	<h1 class="title">掲示板ログイン</h1>
	<br>
	<form action="index.php" method="post">
		<h2>ユーザーID</h2>
		<input name="userid" class="userids" value="<?php echo $userid; ?>" required>

		<h2>パスワード</h2>
		<input name="password" type="password" value="<?php echo $pass;?>" class="passwords" required><br><br>

		<button class="button1" type="submit">ログイン</button><br>
		<p style="color: red"><?php echo $msg; ?></p>
	</form>
	<br><hr style="height: 2px; background-color: black;"><br>
	<button class="button1" onclick="location.href='php/signup.php'">アカウント申請</button>
	<button class="button1" onclick="location.href='php/users.php'">ゲストとして使用</button>
	<?php }
	else { ?>
	<center>
	<h1 class="title">掲示板</h1>
	<br>
	<br>
	<h2>メンテナンス中</h2>
	<br>
	<h3><?php echo $comment; ?></h3><br>
	<h3><?php echo $starttime; ?>　から　<?php echo $endtime; ?>　までの予定でございます。</h3>
	<br><hr style="height: 2px; background-color: black;"><br>

<?php } ?>
</body>
</html>