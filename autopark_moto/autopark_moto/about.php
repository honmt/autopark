<?php
session_start();
$page_title = "О системе | AUTOPARK MOTO";
$current_page = "about";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Подключаем общие стили -->
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    
    <!-- Подключаем стили для страницы "О системе" -->
    <link rel="stylesheet" href="/autopark_moto/assets/css/about.css">
    
    <!-- Иконки Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<!-- Хедер -->
<?php include 'includes/header.php'; ?>

<!-- Основной контент -->
<main>
    <div class="about-container">
        <div class="about-header">
            <h1 class="about-title">
                <i class="fas fa-info-circle"></i> О системе AUTOPARK MOTO
            </h1>
            <p class="about-subtitle">
                Инновационная веб-система для управления автопарком предприятия с прогнозом ТО
            </p>
        </div>
        
        <!-- Что такое AUTOPARK MOTO -->
        <section class="about-section">
            <h2><i class="fas fa-cogs"></i> Что такое AUTOPARK MOTO?</h2>
            <p><strong>AUTOPARK MOTO</strong> - это комплексная веб-система, разработанная для эффективного управления автопарком предприятия. Наша система позволяет отслеживать состояние транспортных средств, планировать техническое обслуживание и прогнозировать будущие ремонты на основе интеллектуального анализа данных.</p>
        </section>
        
        <!-- Ключевые возможности -->
        <section class="about-section">
            <h2><i class="fas fa-bolt"></i> Ключевые возможности</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-car-alt"></i>
                    </div>
                    <h3>Управление автопарком</h3>
                    <p>Централизованный учет всех транспортных средств предприятия с детальной информацией по каждому объекту</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>График ТО</h3>
                    <p>Автоматическое составление и контроль графика технического обслуживания для каждого транспортного средства</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Прогноз ТО</h3>
                    <p>Интеллектуальный прогноз будущего технического обслуживания на основе пробега и истории эксплуатации</p>
                </div>
            </div>
        </section>
        
        <!-- Основные возможности системы -->
        <section class="about-section">
            <h2><i class="fas fa-star"></i> Основные возможности системы</h2>
            <ul class="capabilities-list">
                <li>
                    <strong>Централизованная база данных транспортных средств</strong>
                    <p>Хранение полной информации о каждом транспортном средстве: технические характеристики, история обслуживания, документы</p>
                </li>
                <li>
                    <strong>Интеллектуальный прогноз технического обслуживания</strong>
                    <p>Алгоритмы машинного обучения анализируют данные и предсказывают оптимальное время для проведения ТО</p>
                </li>
                <li>
                    <strong>Автоматические уведомления и напоминания</strong>
                    <p>Система отправляет оповещения о предстоящем ТО по электронной почте и внутри системы</p>
                </li>
                <li>
                    <strong>Аналитика и отчетность</strong>
                    <p>Генерация отчетов по затратам на обслуживание, простою транспортных средств и эффективности использования</p>
                </li>
                <li>
                    <strong>Мобильная адаптация</strong>
                    <p>Полнофункциональный веб-интерфейс, адаптированный для использования на компьютерах, планшетах и смартфонах</p>
                </li>
            </ul>
        </section>
        
        <!-- Технологический стек -->
        <section class="about-section">
            <h2><i class="fas fa-code"></i> Технологический стек</h2>
            <p>Система разработана с использованием современных веб-технологий, обеспечивающих надежность, безопасность и производительность:</p>
            
            <div class="tech-stack">
                <div class="tech-category">
                    <h4>Frontend</h4>
                    <div class="tech-tags">
                        <span class="tech-tag">HTML5</span>
                        <span class="tech-tag">CSS3</span>
                        <span class="tech-tag">JavaScript</span>
                        <span class="tech-tag">Responsive Design</span>
                    </div>
                </div>
                
                <div class="tech-category">
                    <h4>Backend</h4>
                    <div class="tech-tags">
                        <span class="tech-tag">PHP 8+</span>
                        <span class="tech-tag">MySQL</span>
                        <span class="tech-tag">REST API</span>
                    </div>
                </div>
                
                <div class="tech-category">
                    <h4>Безопасность</h4>
                    <div class="tech-tags">
                        <span class="tech-tag">HTTPS</span>
                        <span class="tech-tag">Хеширование паролей</span>
                        <span class="tech-tag">SQL-инъекции защита</span>
                        <span class="tech-tag">CSRF-токены</span>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Контакты -->
        <section class="about-section">
            <h2><i class="fas fa-envelope"></i> Контакты и поддержка</h2>
            <div class="contact-grid">
                <div class="contact-item">
                    <i class="fas fa-headset"></i>
                    <h4>Техническая поддержка</h4>
                    <p>support@autopark-moto.ru</p>
                    <p>+7 (495) 123-45-67</p>
                    <p>Пн-Пт: 9:00-18:00</p>
                </div>
                
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <h4>Наш офис</h4>
                    <p>г. Москва, ул. Автозаводская, д. 15</p>
                    <p>Бизнес-центр "Мотор"</p>
                </div>
                
                <div class="contact-item">
                    <i class="fas fa-lightbulb"></i>
                    <h4>Предложения по развитию</h4>
                    <p>ideas@autopark-moto.ru</p>
                    <p>Мы открыты к новым идеям и сотрудничеству</p>
                </div>
            </div>
        </section>
    </div>
</main>

<!-- Футер -->
<?php include 'includes/footer.php'; ?>

</body>
</html>