<?php require_once "auth.php"; ?>

<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Dynamic title -->
<title><?= htmlspecialchars($page_title ?? "КнигоМания") ?></title>

<!-- SEO -->
<meta name="description" content="КнигоМания – онлайн платформа за нови книги, книги втора ръка и учебници. Открийте следващата си любима книга.">

<!-- Favicon -->
<link rel="icon" type="image/png" href="book32.png">

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Main CSS -->
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="footer.css">
<link rel="stylesheet" href="auth.css">

<!-- Layout fix -->
<style>

html{
  background:#f8f3ea !important;
}

body.km-layout{
  display:flex !important;
  flex-direction:column !important;
  min-height:100vh !important;
}

body.km-layout main{
  flex:1 1 auto !important;
}

body.km-layout footer{
  flex-shrink:0 !important;
}

</style>

</head>