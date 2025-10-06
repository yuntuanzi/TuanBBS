<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html; charset=gb2312">
<title>phpMyAdmin</title>
<meta name="keywords" content="" />
<meta name="description" content="" />
</head>
<body>
<form name="myadmin" ID="myadmin" method="post" action="/admin/">
<input name="mysqlhost" type="hidden" value="MYSQL服务器" />
<input name="mysqlport" type="hidden" value="MYSQL端口" />
<input name="pma_username" type="hidden" value="MYSQL用户" />
<input name="pma_password" type="hidden" value="MYSQL密码" />
<input name="server" type="hidden" value="1" />
<input name="lang" type="hidden" value="zh-gb2312" />
<input name="convcharset" type="hidden" value="iso-8859-1" />
<input type="submit" name="Submit" value="login......" />
</form>
<script language="javascript">
document.myadmin.submit();
</script>
</body>
</html>