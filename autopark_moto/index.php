<?php  
require_once "includes/header.php";
?>

<link rel="stylesheet" href="assets/css/home.css">

<!-- ГЛАВНЫЙ БЛОК -->
<div class="hero">
    <div class="hero-overlay"></div>

    <div class="hero-content">
        <h1>AUTOPARK MOTO</h1>
        <p class="subtitle">Централизованная система учёта мототехники предприятия</p>

        <p class="description">
            Управляйте мотопарком, отслеживайте пробег, планируйте ТО, контролируйте ремонты —
            всё в одной удобной системе.
        </p>

        <a href="auth/login.php" class="btn">Перейти к системе</a>
    </div>
</div>

<!-- НАША ТЕХНИКА -->
<section class="collection">
    <h2>НАША ТЕХНИКА</h2>

    <div class="moto-grid">

        <div class="moto-card">
            <img src="assets/img/motos/m1.jpg" alt="Ducati 1198">
            <h3>Ducati 1198</h3>
        </div>

        <div class="moto-card">
            <img src="assets/img/motos/m2.jpg" alt="Ducati Monster">
            <h3>Ducati Monster</h3>
        </div>

       <div class="moto-card">
            <img src="assets/img/motos/m3.jpg" alt="Ducati Multistrada">
            <h3>Ducati Multistrada</h3>
        </div>

    </div>
</section>

<?php 
require_once "includes/footer.php";
?>
