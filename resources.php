<?php
$page_title = "Immigration Resources | Visafy";
include('includes/functions.php');
include('includes/header.php');
?>

<section class="hero hero-small">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="page-title">Immigration Resources</h1>
            <p class="page-subtitle">Helpful guides, tools and information for your immigration journey</p>
        </div>
    </div>
</section>

<section class="resources-section">
    <div class="container">
        <div class="resources-grid">
            <div class="resource-card" data-aos="fade-up" data-aos-delay="100">
                <div class="resource-icon">
                    <i class="fas fa-newspaper"></i>
                </div>
                <h3 class="resource-title">Blog</h3>
                <p class="resource-description">Stay updated with the latest immigration news, policy changes, and success stories.</p>
                <a href="/resources/blog.php" class="resource-link">Read Articles <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="resource-card" data-aos="fade-up" data-aos-delay="200">
                <div class="resource-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <h3 class="resource-title">FAQs</h3>
                <p class="resource-description">Find answers to commonly asked questions about Canadian immigration.</p>
                <a href="/resources/faq.php" class="resource-link">View FAQs <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="resource-card" data-aos="fade-up" data-aos-delay="300">
                <div class="resource-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3 class="resource-title">Document Checklists</h3>
                <p class="resource-description">Comprehensive lists of required documents for different immigration programs.</p>
                <a href="/resources/documents.php" class="resource-link">View Checklists <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="resource-card" data-aos="fade-up" data-aos-delay="400">
                <div class="resource-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <h3 class="resource-title">Calculators</h3>
                <p class="resource-description">Tools to calculate your CRS score, language levels, and more.</p>
                <a href="/resources/calculators.php" class="resource-link">Use Calculators <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="resource-card" data-aos="fade-up" data-aos-delay="500">
                <div class="resource-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <h3 class="resource-title">Country Guides</h3>
                <p class="resource-description">Specific information for applicants from different countries.</p>
                <a href="/resources/country-guides.php" class="resource-link">View Guides <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="resource-card" data-aos="fade-up" data-aos-delay="600">
                <div class="resource-icon">
                    <i class="fas fa-video"></i>
                </div>
                <h3 class="resource-title">Webinars</h3>
                <p class="resource-description">Recorded and upcoming webinars on various immigration topics.</p>
                <a href="/resources/webinars.php" class="resource-link">Watch Webinars <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<section class="cta-section bg-light">
    <div class="container">
        <div class="cta-content text-center">
            <h2 class="cta-title">Need Personalized Help?</h2>
            <p class="cta-text">Our immigration consultants can provide tailored guidance for your specific situation.</p>
            <div class="cta-buttons">
                <a href="/eligibility-test.php" class="btn btn-primary">Check Eligibility</a>
                <a href="/contact.php" class="btn btn-secondary">Contact Us</a>
            </div>
        </div>
    </div>
</section>

<style>
    .hero-small {
        padding: 80px 0 60px;
        background-color: #042167;
        color: #fff;
    }
    
    .page-title {
        margin-bottom: 15px;
        font-size: 2.5rem;
    }
    
    .page-subtitle {
        font-size: 1.2rem;
        max-width: 800px;
        margin: 0 auto;
    }
    
    .resources-section {
        padding: 80px 0;
    }
    
    .resources-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
    }
    
    .resource-card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        padding: 30px;
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .resource-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .resource-icon {
        font-size: 2.5rem;
        color: #eaaa34;
        margin-bottom: 20px;
    }
    
    .resource-title {
        font-size: 1.5rem;
        color: #042167;
        margin-bottom: 15px;
    }
    
    .resource-description {
        color: #666;
        margin-bottom: 20px;
        flex-grow: 1;
    }
    
    .resource-link {
        color: #eaaa34;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: color 0.3s ease;
    }
    
    .resource-link i {
        margin-left: 8px;
        transition: transform 0.3s ease;
    }
    
    .resource-link:hover {
        color: #042167;
    }
    
    .resource-link:hover i {
        transform: translateX(5px);
    }
    
    .bg-light {
        background-color: #f8f9fa;
    }
    
    .cta-section {
        padding: 80px 0;
    }
    
    .cta-content {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .cta-title {
        font-size: 2rem;
        color: #042167;
        margin-bottom: 15px;
    }
    
    .cta-text {
        font-size: 1.1rem;
        margin-bottom: 30px;
    }
    
    .cta-buttons {
        display: flex;
        justify-content: center;
        gap: 15px;
    }
    
    @media (max-width: 992px) {
        .resources-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .resources-grid {
            grid-template-columns: 1fr;
        }
        
        .cta-buttons {
            flex-direction: column;
            gap: 10px;
            max-width: 300px;
            margin: 0 auto;
        }
    }
</style>

<?php include('includes/footer.php'); ?> 