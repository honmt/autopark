<?php
// model.php
// Страница просмотра модели мотоцикла. Попробует взять данные из БД по ?id=ID,
// иначе покажет статический пример Ducati 1198.

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/db.php';

// По умолчанию — статичные данные (на случай, если БД пустая)
$default = [
    'id' => 0,
    'plate' => 'DUC-1198',
    'model' => 'Ducati 1198',
    'make' => 'Ducati',
    'year' => '2011',
    'odometer' => 12000,
    'description' => "Ducati 1198 — спортивный мотоцикл с отличной управляемостью и богатой историей. Отличается мощным двигателем и характерным дизайном.",
    'image' => '/autopark_moto/assets/img/motos/m1.jpg',
    'gallery' => [
        '/autopark_moto/assets/img/motos/m1.jpg',
        '/autopark_moto/assets/img/motos/m2.jpg',
        '/autopark_moto/assets/img/motos/m3.jpg',
    ],
    // технические характеристики для отображения
    'specs' => [
        'Двигатель' => '1198 cc, L-twin',
        'Мощность' => '170 hp',
        'Крутящий момент' => '131 Nm',
        'Трансмиссия' => '6-speed',
        'Вес (сухой)' => '169 kg',
        'Топливо' => 'Бензин',
    ]
];

$moto = $default;
if (!empty($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('SELECT * FROM motorcycles WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $moto = $row;
        // prepare some fields if not present
        if (empty($moto['image'])) $moto['image'] = $default['image'];
        // gallery can be derived or static
        $moto['gallery'] = $default['gallery'];
        $moto['specs'] = $default['specs'];
        if (empty($moto['description'])) $moto['description'] = $default['description'];
    }
}

// Подключаем стили страницы модели
?>
<link rel="stylesheet" href="/autopark_moto/assets/css/model.css">

<div class="model-page">
  <div class="model-hero">
    <div class="hero-left">
      <div class="big-photo">
        <img src="<?php echo htmlspecialchars($moto['image']); ?>" alt="<?php echo htmlspecialchars($moto['model']); ?>">
      </div>
      <div class="gallery">
        <?php foreach ($moto['gallery'] as $g): ?>
          <div class="thumb"><img src="<?php echo htmlspecialchars($g); ?>" alt=""></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="hero-right">
      <h1 class="model-title"><?php echo htmlspecialchars($moto['model']); ?></h1>
      <div class="meta">
        <span class="make"><?php echo htmlspecialchars($moto['make']); ?></span>
        <span class="year"><?php echo htmlspecialchars($moto['year']); ?></span>
        <span class="odo">Пробег: <?php echo number_format($moto['odometer'], 0, ',', ' '); ?> км</span>
      </div>

      <p class="description"><?php echo nl2br(htmlspecialchars($moto['description'])); ?></p>

      <div class="specs">
        <h3>Технические характеристики</h3>
        <table>
          <?php foreach ($moto['specs'] as $k => $v): ?>
            <tr><th><?php echo htmlspecialchars($k); ?></th><td><?php echo htmlspecialchars($v); ?></td></tr>
          <?php endforeach; ?>
        </table>
      </div>

      <div class="actions">
        <!-- кнопка заявки на ТО/ремонт -->
        <form method="post" action="/autopark_moto/user/trips.php">
          <!-- В реальной системе можно вести отдельный запрос/заявку; тут просто пример -->
          <input type="hidden" name="motorcycle_id" value="<?php echo (int)$moto['id']; ?>">
          <button class="btn btn-primary" type="submit">Записать на ТО / Оставить заявку</button>
        </form>

        <!-- кнопка к списку -->
        <a href="/autopark_moto/" class="btn btn-ghost">Назад в каталог</a>
      </div>
    </div>
  </div>

  <section class="more-info">
    <div class="info-block">
      <h3>Описание и история</h3>
      <p>Здесь можно разместить большой текст о модели, истории обслуживания, текущих замечаниях по технике и рекомендациях по эксплуатации. Также — ссылки на предыдущие ремонты и отчёты.</p>
    </div>

    <div class="maintenance-block">
      <h3>График ТО и прогноз</h3>
      <?php
      // Попытаемся показать назначенные ТО для этой машины (если есть)
      $ms = $pdo->prepare('SELECT ms.*, t.name as template_name FROM motorcycle_services ms JOIN service_templates t ON t.id = ms.template_id WHERE ms.motorcycle_id = ?');
      $ms->execute([(int)$moto['id']]);
      $list = $ms->fetchAll();
      if ($list):
      ?>
        <table class="services-table">
          <thead><tr><th>Шаблон</th><th>Последний пробег</th><th>След. пробег</th><th>Последняя дата</th><th>След. дата</th><th>Статус</th></tr></thead>
          <tbody>
            <?php foreach ($list as $s): ?>
              <tr>
                <td><?php echo htmlspecialchars($s['template_name']); ?></td>
                <td><?php echo $s['last_service_odometer']; ?></td>
                <td><?php echo $s['next_service_mileage']; ?></td>
                <td><?php echo $s['last_service_date']; ?></td>
                <td><?php echo $s['next_service_date']; ?></td>
                <td><?php echo htmlspecialchars($s['status']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>Для этого мотоцикла ещё не назначено ТО. Перейдите в Админ —> Шаблоны ТО, чтобы назначить.</p>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
