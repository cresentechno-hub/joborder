<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= e(setting('app_name', APP_NAME)) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
  <div class="guest-wrapper">
    <div class="guest-card">
      <div class="guest-logo">
        <img src="<?= asset('images/Letterheadlogo.jpg') ?>" alt="Cresentech">
        <p class="guest-subtitle"><?= e(setting('app_name', APP_NAME)) ?></p>
      </div>
      <div class="card">
        <?= $content ?>
      </div>
    </div>
  </div>
</body>
</html>
