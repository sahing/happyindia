<?php
include 'includes/language.php';
$currentLang = getCurrentLanguage();
$langSwitcher = getLanguageSwitcher();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('site_title'); ?> - HappyIndia.org</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #e91e63;
            --secondary-color: #9c27b0;
            --accent-color: #3f51b5;
        }

        body {
            font-family: 'Segoe UI', 'Nirmala UI', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('https://images.unsplash.com/photo-1580477667995-2b94f01c9516?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            padding: 100px 0;
            color: white;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
        }

        .section-title {
            position: relative;
            margin-bottom: 40px;
            font-weight: 700;
        }

        .section-title:after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            margin-top: 15px;
        }

        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        footer {
            background: #343a40;
            color: white;
            padding: 40px 0;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }

        .language-switcher {
            margin-left: 15px;
        }

        .language-switcher .dropdown-toggle::after {
            margin-left: 5px;
        }

        .rules-content .rule-item {
            padding: 15px;
            border-left: 4px solid var(--primary-color);
            background-color: rgba(233, 30, 99, 0.05);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .rules-content .rule-item:hover {
            background-color: rgba(233, 30, 99, 0.1);
            transform: translateX(5px);
        }

        .rules-content .rule-item i {
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-smile"></i> <?php echo __('site_title'); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><?php echo __('nav_home'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="user/register.php"><?php echo __('nav_register'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="user/payment.php"><?php echo __('make_payment'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact"><?php echo __('nav_contact'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-light ms-2" href="user/login.php"><?php echo __('nav_login'); ?></a>
                    </li>
                    <!-- Language Switcher -->
                    <li class="nav-item dropdown language-switcher">
                        <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-globe"></i> <?php echo __('language'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($langSwitcher as $code => $lang): ?>
                                <li><a class="dropdown-item <?php echo $lang['active'] ? 'active' : ''; ?>" href="<?php echo $lang['url']; ?>">
                                    <?php echo $lang['name']; ?>
                                </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold"><?php echo __('hero_title'); ?></h1>
            <p class="lead"><?php echo __('hero_subtitle'); ?></p>
            <a href="user/register.php" class="btn btn-primary btn-lg mt-3"><?php echo __('get_started'); ?></a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center section-title"><?php echo __('how_it_works_title'); ?></h2>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h4><?php echo __('step_1_title'); ?></h4>
                        <p><?php echo __('step_1_desc'); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <h4><?php echo __('step_2_title'); ?></h4>
                        <p><?php echo __('step_2_desc'); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="fas fa-share-alt"></i>
                        </div>
                        <h4><?php echo __('step_3_title'); ?></h4>
                        <p><?php echo __('step_3_desc'); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <h4><?php echo __('step_4_title'); ?></h4>
                        <p><?php echo __('step_4_desc'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center section-title"><?php echo __('how_it_works_title'); ?></h2>
            <div class="row">
                <div class="col-lg-6">
                    <div class="d-flex mb-4">
                        <div class="step-number">1</div>
                        <div>
                            <h4><?php echo __('step_1_title'); ?></h4>
                            <p><?php echo __('step_1_desc'); ?></p>
                        </div>
                    </div>
                    <div class="d-flex mb-4">
                        <div class="step-number">2</div>
                        <div>
                            <h4><?php echo __('step_2_title'); ?></h4>
                            <p><?php echo __('step_2_desc'); ?></p>
                        </div>
                    </div>
                    <div class="d-flex mb-4">
                        <div class="step-number">3</div>
                        <div>
                            <h4><?php echo __('step_3_title'); ?></h4>
                            <p><?php echo __('step_3_desc'); ?></p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <div class="step-number">4</div>
                        <div>
                            <h4><?php echo __('step_4_title'); ?></h4>
                            <p><?php echo __('step_4_desc'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="https://images.unsplash.com/photo-1579621970563-ebec7560ff3e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80" class="img-fluid rounded" alt="Process">
                </div>
            </div>
        </div>
    </section>

    <!-- Company Rules Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center section-title"><?php echo __('rules_title'); ?></h2>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card p-4">
                        <div class="rules-content">
                            <div class="rule-item mb-4">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong><?php echo __('rule_1'); ?></strong>
                            </div>
                            <div class="rule-item mb-4">
                                <i class="fas fa-clock text-primary me-2"></i>
                                <strong><?php echo __('rule_2'); ?></strong>
                            </div>
                            <div class="rule-item mb-4">
                                <i class="fas fa-id-card text-info me-2"></i>
                                <strong><?php echo __('rule_3'); ?></strong>
                            </div>
                            <div class="rule-item mb-4">
                                <i class="fas fa-rupee-sign text-success me-2"></i>
                                <strong><?php echo __('rule_4'); ?></strong>
                            </div>
                            <div class="rule-item mb-4">
                                <i class="fas fa-rupee-sign text-success me-2"></i>
                                <strong><?php echo __('rule_5'); ?></strong>
                            </div>
                            <div class="rule-item mb-4">
                                <i class="fas fa-rupee-sign text-success me-2"></i>
                                <strong><?php echo __('rule_6'); ?></strong>
                            </div>
                            <div class="rule-item mb-4">
                                <i class="fas fa-briefcase text-warning me-2"></i>
                                <strong><?php echo __('rule_7'); ?></strong>
                            </div>
                            <div class="rule-item mb-4">
                                <i class="fas fa-user-tie text-dark me-2"></i>
                                <strong><?php echo __('rule_8'); ?></strong>
                            </div>
                            <div class="rule-item mb-4">
                                <i class="fas fa-money-bill-wave text-success me-2"></i>
                                <strong><?php echo __('rule_9'); ?></strong>
                            </div>
                            <div class="rule-item mb-4">
                                <i class="fas fa-building text-primary me-2"></i>
                                <strong><?php echo __('rule_10'); ?></strong>
                            </div>
                            <div class="rule-item mb-4">
                                <i class="fas fa-handshake text-info me-2"></i>
                                <strong><?php echo __('rule_11'); ?></strong>
                            </div>
                            <div class="rule-item mb-4">
                                <i class="fas fa-users text-primary me-2"></i>
                                <strong><?php echo __('rule_12'); ?></strong>
                            </div>
                            <div class="rule-item">
                                <i class="fas fa-graduation-cap text-success me-2"></i>
                                <strong><?php echo __('rule_13'); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Info -->
    <section class="py-5" id="contact">
        <div class="container">
            <h2 class="text-center section-title"><?php echo __('contact_title'); ?></h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="card p-4">
                        <h4><?php echo __('contact_info'); ?></h4>
                        <p><i class="fas fa-map-marker-alt me-2"></i> 29 No Taltala Lane, Kolkata – 700016</p>
                        <hr>
                        <h4>Executive Directors</h4>
                        <p><i class="fas fa-user me-2"></i> Md. Jamal and Md. Milon</p>
                        <h4>Branch Manager</h4>
                        <p><i class="fas fa-user me-2"></i> Prakash Mondol</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-4">
                        <h4>Website Information</h4>
                        <p><i class="fas fa-globe me-2"></i> Domain: HappyIndia.org</p>
                        <p><i class="fas fa-envelope me-2"></i> Email: info@happyindia.org</p>
                        <hr>
                        <h4>Working Hours</h4>
                        <p><i class="fas fa-clock me-2"></i> Monday - Saturday: 10 AM - 6 PM</p>
                        <p><i class="fas fa-clock me-2"></i> Sunday: Closed</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><?php echo __('site_title'); ?></h5>
                    <p><?php echo __('footer_about'); ?></p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5><?php echo __('footer_links'); ?></h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white">Privacy Policy</a></li>
                        <li><a href="#" class="text-white">Terms of Service</a></li>
                        <li><a href="#" class="text-white">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Follow Us</h5>
                    <div class="d-flex justify-content-center">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-youtube fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr class="mt-4">
            <p>&copy; 2025 HappyIndia.org - All Rights Reserved</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>