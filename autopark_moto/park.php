<?php
require_once "includes/header.php";

// Если у тебя есть база — здесь подключается $pdo
// require_once "includes/db.php";

// Если техники в БД еще нет — пока выводим вручную:
$moto = [
    [
        "name" => "Ducati 1198",
        "img"  => "assets/img/motos/m1.jpg",
        "desc" => "1198cc, 170 л.с., спортивный мотоцикл"
    ],
    [
        "name" => "Ducati Monster",
        "img"  => "assets/img/motos/m2.jpg",
        "desc" => "1100cc, спортивный мотоцикл"
    ],
    [
        "name" => "Ducati Multistrada",
        "img"  => "assets/img/motos/m3.jpg",
        "desc" => "1200cc, спортивный мотоцикл"
    ]
];
?>

<link rel="stylesheet" href="assets/css/park.css">

<div class="park-container">

    <h1 class="park-title">AUTOPARK MOTO</h1>

    <div class="park-grid">
        <?php foreach ($moto as $item): ?>
            <div class="moto-card">
                <img src="<?= $item['img'] ?>" class="moto-img">
                <h3><?= $item['name'] ?></h3>
                <p class="moto-desc"><?= $item['desc'] ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="park-page">
    <img src="assets/img/main.jpg" class="bg-image">

    <div class="content">
        <!-- весь контент страницы -->
    </div>
</div>

</div>

<?php
require_once "includes/footer.php";
?>
