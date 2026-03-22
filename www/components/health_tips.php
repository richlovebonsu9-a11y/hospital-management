<?php
// Health Tips Slideshow Component
$healthTips = [
    ["title" => "Stay Hydrated", "tip" => "Drink at least 8 glasses of water a day to maintain your body's vital functions and keep your skin healthy.", "icon" => "bi-droplet-fill"],
    ["title" => "Balanced Diet", "tip" => "Incorporate a variety of fruits, vegetables, lean proteins, and whole grains into your daily meals.", "icon" => "bi-egg-fried"],
    ["title" => "Regular Exercise", "tip" => "Aim for at least 30 minutes of moderate physical activity every day to keep your heart strong.", "icon" => "bi-bicycle"],
    ["title" => "Adequate Sleep", "tip" => "Adults should get 7-9 hours of quality sleep per night for optimal brain function and recovery.", "icon" => "bi-moon-stars-fill"],
    ["title" => "Mental Health", "tip" => "Take time to relax and de-stress. Practice mindfulness, meditation, or simply enjoy a quiet hobby.", "icon" => "bi-peace"],
    ["title" => "Wash Your Hands", "tip" => "Frequent handwashing with soap and water for 20 seconds prevents the spread of infections.", "icon" => "bi-water"],
    ["title" => "Limit Sugar Intake", "tip" => "Reduce consumption of sugary drinks and snacks to lower the risk of diabetes and obesity.", "icon" => "bi-cup-straw"],
    ["title" => "Regular Check-ups", "tip" => "Don't skip your annual physical exams. Early detection is key to treating many health issues.", "icon" => "bi-clipboard2-pulse"],
    ["title" => "Protect Your Skin", "tip" => "Wear sunscreen outdoors to protect your skin from harmful UV rays and prevent premature aging.", "icon" => "bi-sun-fill"],
    ["title" => "Posture Matters", "tip" => "Sit and stand with good posture to prevent back and neck pain, especially if you work at a desk.", "icon" => "bi-person-standing"]
];
?>

<!-- Dynamic Premium Health Tips Carousel -->
<div class="d-flex justify-content-end mb-4">
    <div class="card border-0 rounded-4 overflow-hidden text-white" style="max-width: 360px; width: 100%; background: linear-gradient(135deg, #4A00E0 0%, #8E2DE2 100%); box-shadow: 0 12px 24px rgba(142, 45, 226, 0.25) !important;">
        
        <!-- Decorative subtle pattern overlay -->
        <div class="position-absolute w-100 h-100 opacity-25" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 20px 20px; pointer-events: none;"></div>

        <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex align-items-center position-relative z-1">
            <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-2 shadow-sm" style="width: 28px; height: 28px; backdrop-filter: blur(4px);">
                <i class="bi bi-stars text-warning" style="font-size: 1rem;"></i>
            </div>
            <h6 class="fw-bold mb-0 text-white text-uppercase" style="letter-spacing: 1px; font-size: 0.75rem;">Daily Wellness Tip</h6>
        </div>
        
        <div class="card-body p-3 pt-2 position-relative z-1">
            <div id="healthTipsCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="6000">
                <div class="carousel-inner py-1">
                    <?php foreach ($healthTips as $index => $item): ?>
                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                            <div class="d-flex align-items-start gap-3 px-2">
                                <div class="bg-white rounded-4 shadow-sm d-flex align-items-center justify-content-center flex-shrink-0" style="width: 50px; height: 50px;">
                                    <i class="bi <?php echo $item['icon']; ?> fs-3" style="background: -webkit-linear-gradient(135deg, #4A00E0, #8E2DE2); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-white mb-1" style="font-size: 1rem;"><?php echo htmlspecialchars($item['title']); ?></h6>
                                    <p class="mb-0 text-white" style="font-size: 0.9rem; line-height: 1.5; font-weight: 500; opacity: 0.95;"><?php echo htmlspecialchars($item['tip']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Invisible controls to maintain functionality if user swipes, but out of the way -->
                <button class="carousel-control-prev" type="button" data-bs-target="#healthTipsCarousel" data-bs-slide="prev" style="width: 5%; opacity: 0;">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#healthTipsCarousel" data-bs-slide="next" style="width: 5%; opacity: 0;">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
        
        <!-- Subtle progress bar effect at the bottom -->
        <div class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: rgba(255,255,255,0.1);">
            <div class="h-100 bg-warning" style="width: 100%; animation: slideProgress 6s linear infinite;"></div>
        </div>
        
        <style>
            @keyframes slideProgress {
                0% { width: 0%; opacity: 1; }
                95% { width: 100%; opacity: 1; }
                100% { width: 100%; opacity: 0; }
            }
        </style>
    </div>
</div>
