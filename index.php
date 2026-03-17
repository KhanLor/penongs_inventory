<?php
// Redirect to login if user is already authenticated
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] == 'admin' ? 'admin_dashboard.php' : 'manager_dashboard.php'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penongs Inventory System - Modern Food Inventory Management</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #F5F7FA;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Landing Hero Section */
        .landing-hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
            padding: 80px 0;
            background: linear-gradient(135deg, #FFFFFF 0%, #F8F9FA 100%);
            border-radius: 20px;
            padding: 60px 50px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }
        
        .hero-logo {
            font-size: 56px;
            margin-bottom: 20px;
        }
        
        .hero-title {
            font-size: 42px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .hero-subtitle {
            font-size: 18px;
            color: #7F8C8D;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .features-list {
            list-style: none;
            margin-bottom: 30px;
        }
        
        .features-list li {
            padding: 10px 0;
            padding-left: 30px;
            position: relative;
            color: #555;
        }
        
        .features-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #F4D03F;
            font-weight: bold;
            font-size: 18px;
        }
        
        .btn-container {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(244, 208, 63, 0.3);
        }
        
        .btn-secondary {
            background: white;
            color: #F4D03F;
            border: 2px solid #F4D03F;
        }
        
        .btn-secondary:hover {
            background: #F4D03F;
            color: white;
        }
        
        .hero-image {
            text-align: center;
            padding: 40px;
            background: linear-gradient(135deg, #F4D03F20 0%, #F39C1220 100%);
            border-radius: 15px;
        }
        
        .hero-image-icon {
            font-size: 120px;
            margin-bottom: 20px;
        }
        
        .hero-image-text {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .hero-image-desc {
            font-size: 14px;
            color: #7F8C8D;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 40px;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: #FEF5E7;
            border-radius: 10px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #F39C12;
        }
        
        .stat-label {
            font-size: 13px;
            color: #7F8C8D;
            margin-top: 5px;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            color: #7F8C8D;
            font-size: 13px;
            padding-top: 20px;
            border-top: 1px solid #E8E8E8;
        }
        
        .footer p {
            margin: 10px 0;
        }
        
        .footer a {
            color: #7F8C8D;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer a:hover {
            color: #F4D03F;
            text-decoration: underline;
        }
        
        /* Developer Acknowledgement */
        .developer-section {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .developer-section h3 {
            font-size: 22px;
            color: #333;
            margin-bottom: 25px;
            font-weight: 700;
        }
        
        .developer-card {
            display: inline-block;
            padding: 30px 40px;
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            border-radius: 15px;
            color: white;
            box-shadow: 0 5px 20px rgba(244, 208, 63, 0.3);
            transition: all 0.3s ease;
        }
        
        .developer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(244, 208, 63, 0.4);
        }
        
        .developer-name {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }
        
        .developer-role {
            font-size: 16px;
            font-weight: 600;
            opacity: 0.95;
        }
        
        .developer-description {
            font-size: 14px;
            margin-top: 15px;
            opacity: 0.9;
            line-height: 1.6;
            max-width: 400px;
        }
        
        /* ===== TESTIMONIALS SECTION ===== */
        .testimonials-section {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }
        
        .section-title {
            text-align: center;
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .section-subtitle {
            text-align: center;
            color: #7F8C8D;
            margin-bottom: 40px;
            font-size: 16px;
        }
        
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .testimonial-card {
            padding: 30px;
            background: #F8F9FA;
            border-radius: 15px;
            border-left: 4px solid #F4D03F;
            transition: all 0.3s ease;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .testimonial-text {
            font-size: 15px;
            color: #555;
            margin-bottom: 20px;
            font-style: italic;
            line-height: 1.6;
        }
        
        .testimonial-author {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .testimonial-role {
            font-size: 12px;
            color: #F39C12;
        }
        
        .stars {
            color: #F39C12;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        /* ===== FAQ SECTION ===== */
        .faq-section {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }
        
        .faq-item {
            margin-bottom: 20px;
            border: 2px solid #E8E8E8;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .faq-question {
            padding: 20px;
            background: #F8F9FA;
            cursor: pointer;
            font-weight: 600;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .faq-question:hover {
            background: #F4D03F;
            color: white;
        }
        
        .faq-answer {
            padding: 20px;
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            display: none;
            background: white;
        }
        
        .faq-answer.active {
            display: block;
        }
        
        .faq-toggle {
            font-size: 20px;
            transition: transform 0.3s ease;
        }
        
        .faq-toggle.active {
            transform: rotate(180deg);
        }
        
        /* ===== ABOUT SECTION ===== */
        .about-section {
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            border-radius: 20px;
            padding: 60px 40px;
            margin-top: 30px;
            color: white;
            text-align: center;
        }
        
        .about-section h2 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        
        .about-section p {
            font-size: 16px;
            line-height: 1.8;
            max-width: 700px;
            margin: 0 auto 30px;
            opacity: 0.95;
        }
        
        /* ===== BENEFITS CARDS SECTION ===== */
        .benefits-section {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }
        
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .benefit-card {
            padding: 30px;
            background: #F8F9FA;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
            user-select: none;
        }
        
        .benefit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #F4D03F;
            background: #FFFBF0;
        }
        
        .benefit-card:active {
            transform: translateY(-2px);
        }
        
        .benefit-card:focus {
            outline: none;
        }
        
        .benefit-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .benefit-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .benefit-desc {
            font-size: 14px;
            color: #7F8C8D;
            line-height: 1.6;
        }
        
        /* ===== SCREENSHOTS SECTION ===== */
        .screenshots-section {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }
        
        .screenshots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .screenshot-card {
            border-radius: 15px;
            overflow: hidden;
            background: #F0F0F0;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: 2px solid #E8E8E8;
        }
        
        .screenshot-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #F4D03F;
        }
        
        .screenshot-content {
            text-align: center;
        }
        
        .screenshot-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .screenshot-label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        /* ===== ARCHITECTURE SECTION ===== */
        .architecture-section {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }
        
        .tech-stack {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .tech-item {
            padding: 12px 20px;
            background: #FEF5E7;
            border: 2px solid #F4D03F;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #F39C12;
        }
        
        /* ===== TEAM SECTION ===== */
        .team-section {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }
        
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .team-card {
            text-align: center;
            padding: 30px;
            background: #F8F9FA;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .team-card:hover {
            transform: translateY(-5px);
            background: #FEF5E7;
        }
        
        .team-avatar {
            font-size: 60px;
            margin-bottom: 15px;
        }
        
        .team-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .team-role {
            font-size: 13px;
            color: #F39C12;
            margin-bottom: 10px;
        }
        
        .team-desc {
            font-size: 13px;
            color: #7F8C8D;
            line-height: 1.5;
        }
        
        /* ===== CONTACT SECTION ===== */
        .contact-section {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }
        
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .contact-item {
            text-align: center;
        }
        
        .contact-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .contact-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .contact-value {
            color: #7F8C8D;
            font-size: 14px;
        }
        
        /* ===== NEWSLETTER SECTION ===== */
        .newsletter-section {
            background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
            border-radius: 20px;
            padding: 60px 40px;
            margin-top: 30px;
            color: white;
            text-align: center;
        }
        
        .newsletter-section h2 {
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .newsletter-section p {
            font-size: 15px;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .newsletter-form {
            display: flex;
            gap: 10px;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .newsletter-form input {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .newsletter-form button {
            padding: 12px 30px;
            background: #F4D03F;
            color: #333;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .newsletter-form button:hover {
            background: white;
            transform: translateY(-2px);
        }
        
        /* ===== SOCIAL LINKS SECTION ===== */
        .social-section {
            text-align: center;
            margin-top: 60px;
            margin-bottom: 40px;
        }
        
        .social-section h3 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .social-links {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .social-link {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #F4D03F;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 20px;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        
        .social-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(244, 208, 63, 0.3);
            background: #F39C12;
        }
        
        /* ===== HEADER SECTION ===== */
        .main-header {
            background: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
        }
        
        .logo-brand {
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }
        
        .logo-brand:hover {
            opacity: 0.8;
        }
        
        .logo-icon {
            font-size: 32px;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .logo-brand-name {
            font-size: 28px;
            font-weight: 900;
            color: #E74C3C;
            font-style: italic;
            letter-spacing: -1px;
            line-height: 1;
        }
        
        .logo-brand-tagline {
            font-size: 11px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: 1.5px;
            line-height: 1;
        }
        
        .header-nav {
            display: flex;
            gap: 25px;
            align-items: center;
            justify-self: center;
        }
        
        .header-nav a {
            text-decoration: none;
            color: #555;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .header-nav a:hover {
            color: #F4D03F;
        }
        
        .login-btn {
            padding: 8px 20px;
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .header-actions {
            justify-self: end;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 208, 63, 0.3);
        }
        
        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .header-content {
                grid-template-columns: auto 1fr auto;
                gap: 10px;
                padding: 0 14px;
            }

            .header-nav {
                gap: 12px;
                min-width: 0;
            }
            
            .header-nav a {
                display: inline-block;
                font-size: 13px;
                white-space: nowrap;
            }

            .login-btn {
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .logo-text {
                font-size: 18px;
            }
            
            .landing-hero {
                grid-template-columns: 1fr;
                padding: 40px 25px;
            }
            
            .hero-title {
                font-size: 28px;
            }
            
            .hero-image {
                padding: 40px 30px;
            }
            
            .hero-content {
                padding: 40px 30px;
            }
            
            .hero-image-icon {
                font-size: 80px;
            }
            
            .btn-container {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            .testimonials-section, .faq-section, .about-section, .benefits-section,
            .screenshots-section, .architecture-section, .team-section, .contact-section,
            .newsletter-section {
                padding: 40px 25px;
            }
            
            .section-title {
                font-size: 24px;
            }
            
            .newsletter-form {
                flex-direction: column;
            }
            
            .testimonials-grid, .screenshots-grid, .team-grid, .contact-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .logo-brand-name {
                font-size: 24px;
            }

            .header-nav {
                gap: 8px;
            }

            .header-nav a {
                font-size: 12px;
            }

            .login-btn {
                padding: 7px 10px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Main Header -->
    <header class="main-header">
        <div class="header-content">
            <a href="index.php" class="logo-brand">
                <div class="logo-text">
                    <div class="logo-brand-name">Penongs</div>
                </div>
            </a>
            <nav class="header-nav">
                <a href="#benefits">Features</a>
                <a href="#about">About</a>
                <a href="#contact">Contact</a>
            </nav>
            <div class="header-actions">
                <a href="login.php" class="login-btn">🔓 Login</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Landing Hero Section -->
        <div class="landing-hero">
            <!-- Left Content -->
            <div class="hero-content">
                <h1 class="hero-title">Penongs Inventory System</h1>
                <p class="hero-subtitle">Modern daily food inventory management solution for efficient tracking and control</p>
                
                <ul class="features-list">
                    <li>Real-time inventory tracking</li>
                    <li>Multi-branch management</li>
                    <li>Comprehensive reporting</li>
                    <li>Activity logging & security</li>
                    <li>Mobile responsive design</li>
                </ul>
                
             
                
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Availability</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Secure</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">∞</div>
                        <div class="stat-label">Scalable</div>
                    </div>
                </div>
            </div>
            
            <!-- Right Image -->
            <div class="hero-image">
                <div class="hero-image-icon">📦</div>
                <div class="hero-image-text">Smart Inventory</div>
                <div class="hero-image-desc">Manage your food inventory with ease</div>
            </div>
        </div>

        <!-- Key Benefits Cards Section -->
        <div id="benefits" class="benefits-section">
            <h2 class="section-title">Key Features</h2>
            <p class="section-subtitle">Everything you need to manage food inventory efficiently</p>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon">📊</div>
                    <div class="benefit-title">Real-time Dashboard</div>
                    <p class="benefit-desc">Monitor inventory levels, sales, and stock status at a glance with live updates.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">📦</div>
                    <div class="benefit-title">Inventory Tracking</div>
                    <p class="benefit-desc">Track food items, quantities, categories, and expiration dates effortlessly.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">🏢</div>
                    <div class="benefit-title">Multi-Branch Support</div>
                    <p class="benefit-desc">Manage inventory across multiple branches from a single centralized platform.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">📈</div>
                    <div class="benefit-title">Advanced Reports</div>
                    <p class="benefit-desc">Generate detailed inventory reports and analytics for better decision-making.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">👥</div>
                    <div class="benefit-title">User Management</div>
                    <p class="benefit-desc">Control access with role-based permissions for administrators and managers.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">🔒</div>
                    <div class="benefit-title">Secure & Reliable</div>
                    <p class="benefit-desc">Enterprise-grade security with encrypted passwords and secure database management.</p>
                </div>
            </div>
        </div>

        <!-- About Section -->
        <div id="about" class="about-section">
            <h2>Why Choose Penongs?</h2>
            <p>Penongs Inventory System is specifically designed for food businesses to streamline operations, reduce waste, and improve profitability. With an intuitive interface and powerful features, managing your inventory has never been easier. Whether you're running a single location or multiple branches, we've got you covered with a modern, reliable solution.</p>
            <a href="login.php" class="btn btn-primary" style="text-decoration: none; display: inline-block;">Get Started Now</a>
        </div>

        <!-- Testimonials Section -->
        <div class="testimonials-section">
            <h2 class="section-title">What Our Users Say</h2>
            <p class="section-subtitle">Real feedback from real users managing their inventory</p>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="stars">⭐⭐⭐⭐⭐</div>
                    <p class="testimonial-text">"Penongs has transformed how we manage our inventory. The dashboard is intuitive and the reporting features help us make better decisions. Highly recommended!"</p>
                    <div class="testimonial-author">Maria Santos</div>
                    <div class="testimonial-role">Restaurant Manager</div>
                </div>
                <div class="testimonial-card">
                    <div class="stars">⭐⭐⭐⭐⭐</div>
                    <p class="testimonial-text">"We reduced our food waste by 30% after implementing Penongs. The tracking system is accurate and the multi-branch support makes managing our restaurants seamless."</p>
                    <div class="testimonial-author">John Reyes</div>
                    <div class="testimonial-role">Chain Operations Manager</div>
                </div>
                <div class="testimonial-card">
                    <div class="stars">⭐⭐⭐⭐⭐</div>
                    <p class="testimonial-text">"The support team is fantastic and the system is very user-friendly. We've increased our operational efficiency by 25% since we started using it. Best investment!"</p>
                    <div class="testimonial-author">Angela Cruz</div>
                    <div class="testimonial-role">Café Owner</div>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">Find answers to common questions about Penongs</p>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    How do I add a new food item to the inventory?
                    <span class="faq-toggle">▼</span>
                </div>
                <div class="faq-answer">
                    Simply navigate to the Items section from your dashboard and click "Add New Item". Fill in the item details including name, category, cost, and current quantity. You can also set expiration dates and track stock levels.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    Can I use Penongs for multiple branches?
                    <span class="faq-toggle">▼</span>
                </div>
                <div class="faq-answer">
                    Absolutely! Penongs supports unlimited branches. Administrators can manage all branches from a single dashboard, while managers can be assigned to specific branches.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    How are user permissions handled?
                    <span class="faq-toggle">▼</span>
                </div>
                <div class="faq-answer">
                    Penongs uses role-based access control. Administrators have full system access, while managers can only view and manage inventory for their assigned branches.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    Can I generate reports of my inventory history?
                    <span class="faq-toggle">▼</span>
                </div>
                <div class="faq-answer">
                    Yes! The Reports section provides comprehensive inventory analytics including stock history, consumption patterns, and trends. You can filter by date, category, or branch.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    How often should I backup my data?
                    <span class="faq-toggle">▼</span>
                </div>
                <div class="faq-answer">
                    We recommend regular automated backups. Data is stored in a secure MySQL database. Contact support for backup scheduling options. All transactions are logged for audit.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    Is there a mobile app?
                    <span class="faq-toggle">▼</span>
                </div>
                <div class="faq-answer">
                    Our web application is fully responsive and works on all devices including smartphones and tablets. You can access your inventory from anywhere with an internet connection.
                </div>
            </div>
        </div>

        <!-- Screenshots/Demo Section -->
        <div class="screenshots-section">
            <h2 class="section-title">System Interface Preview</h2>
            <p class="section-subtitle">See how Penongs looks and works</p>
            <div class="screenshots-grid">
                <div class="screenshot-card">
                    <div class="screenshot-content">
                        <div class="screenshot-icon">📊</div>
                        <div class="screenshot-label">Dashboard View</div>
                    </div>
                </div>
                <div class="screenshot-card">
                    <div class="screenshot-content">
                        <div class="screenshot-icon">📋</div>
                        <div class="screenshot-label">Inventory List</div>
                    </div>
                </div>
                <div class="screenshot-card">
                    <div class="screenshot-content">
                        <div class="screenshot-icon">📈</div>
                        <div class="screenshot-label">Reports & Analytics</div>
                    </div>
                </div>
                <div class="screenshot-card">
                    <div class="screenshot-content">
                        <div class="screenshot-icon">👥</div>
                        <div class="screenshot-label">User Management</div>
                    </div>
                </div>
                <div class="screenshot-card">
                    <div class="screenshot-content">
                        <div class="screenshot-icon">🏢</div>
                        <div class="screenshot-label">Branch Manager</div>
                    </div>
                </div>
                <div class="screenshot-card">
                    <div class="screenshot-content">
                        <div class="screenshot-icon">⚙️</div>
                        <div class="screenshot-label">Settings & Config</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Members Section -->
        <div class="team-section">
            <h2 class="section-title">Our Development Team</h2>
            <p class="section-subtitle">Dedicated professionals committed to your success</p>
            <div class="team-grid">
                <div class="team-card">
                    <div class="team-avatar">👨‍💻</div>
                    <div class="team-name">Development Team</div>
                    <div class="team-role">Full Stack Developers</div>
                    <p class="team-desc">Building robust inventory solutions with PHP and MySQL. Passionate about creating tools that help businesses succeed.</p>
                </div>
                <div class="team-card">
                    <div class="team-avatar">👩‍💼</div>
                    <div class="team-name">Support Team</div>
                    <div class="team-role">Customer Success</div>
                    <p class="team-desc">Ready to help you get the most out of Penongs with responsive support and helpful guidance.</p>
                </div>
                <div class="team-card">
                    <div class="team-avatar">🔍</div>
                    <div class="team-name">Quality Assurance</div>
                    <div class="team-role">Testing & Security</div>
                    <p class="team-desc">Ensuring Penongs is reliable, secure, and performs excellently. Every feature is thoroughly tested.</p>
                </div>
            </div>
        </div>

        <!-- Contact Section -->
        <div id="contact" class="contact-section">
            <h2 class="section-title">Get in Touch</h2>
            <p class="section-subtitle">We're here to help and answer any questions</p>
            <div class="contact-grid">
                <div class="contact-item">
                    <div class="contact-icon">📧</div>
                    <div class="contact-label">Email</div>
                    <div class="contact-value">inquiries@penongsofficial.ph</div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">📞</div>
                    <div class="contact-label">Phone</div>
                    <div class="contact-value">+63 917 712 9246</div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">📍</div>
                    <div class="contact-label">Address</div>
                    <div class="contact-value">Penong's Head Office: 2nd Floor, McPod Building, McArthur Highway Matina, Davao City, Philippines</div>
                </div>
            </div>
        </div>

        <!-- Social Media Links Section -->
        <div class="social-section">
            <h3>Connect With Us</h3>
            <div class="social-links">
                <a href="https://www.facebook.com/ilovepenongs" class="social-link" title="Facebook" target="_blank">f</a>
                <a href="https://www.instagram.com/ilovepenongs/#" class="social-link" title="Instagram" target="_blank">📷</a>
        
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; 2024 Penongs Inventory System. All rights reserved. </p>
            <p style="font-size: 12px; margin-top: 15px;">Developed with by <strong>Khanyao R. Lor</strong> | IT Developer</p>
        </div>
    </div>

    <script>
        function toggleFaq(element) {
            const answer = element.nextElementSibling;
            const toggle = element.querySelector('.faq-toggle');
            
            // Close other open FAQs
            document.querySelectorAll('.faq-answer.active').forEach(item => {
                if(item !== answer) {
                    item.classList.remove('active');
                    item.previousElementSibling.querySelector('.faq-toggle').classList.remove('active');
                }
            });
            
            // Toggle current FAQ
            answer.classList.toggle('active');
            toggle.classList.toggle('active');
        }
        
        function handleNewsletterSignup(e) {
            e.preventDefault();
            const email = e.target.querySelector('input[type="email"]').value;
            alert('Thank you for subscribing! We will send updates to ' + email);
            e.target.reset();
        }
        
        // Clear benefit card states on page load and interaction
        document.addEventListener('DOMContentLoaded', function() {
            const benefitCards = document.querySelectorAll('.benefit-card');
            benefitCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
                card.addEventListener('click', function(e) {
                    // Prevent default if no link
                    if (e.target.tagName !== 'A') {
                        // Clear any active states
                        benefitCards.forEach(c => {
                            c.style.transform = 'translateY(0)';
                            c.style.boxShadow = '';
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
