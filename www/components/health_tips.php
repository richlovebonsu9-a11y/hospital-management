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

<!-- Dynamic Premium Health Tips Banner - Full Width -->
<div class="mb-5">
    <div class="rounded-4 overflow-hidden text-white position-relative" style="background: linear-gradient(135deg, #4A00E0 0%, #8E2DE2 100%); box-shadow: 0 12px 32px rgba(74, 0, 224, 0.22);">
        <!-- Dot pattern overlay -->
        <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: radial-gradient(rgba(255,255,255,0.12) 1px, transparent 1px); background-size: 22px 22px; pointer-events: none;"></div>
        <!-- Large decorative circle -->
        <div class="position-absolute" style="width: 220px; height: 220px; border-radius: 50%; background: rgba(255,255,255,0.05); right: -40px; top: -60px;"></div>
        <div class="position-absolute" style="width: 140px; height: 140px; border-radius: 50%; background: rgba(255,255,255,0.07); right: 100px; bottom: -50px;"></div>

        <div class="d-flex align-items-center p-4 position-relative" style="z-index: 1;">
            <!-- Left: Label + Icon -->
            <div class="me-4 d-none d-md-flex flex-column align-items-center justify-content-center flex-shrink-0" style="min-width: 80px;">
                <div class="bg-white bg-opacity-20 rounded-4 d-flex align-items-center justify-content-center mb-2 shadow" style="width: 54px; height: 54px; backdrop-filter: blur(6px);">
                    <i class="bi bi-stars fs-3 text-warning"></i>
                </div>
                <span class="text-white fw-bold text-uppercase" style="font-size: 0.6rem; letter-spacing: 1.5px; opacity: 0.85;">Wellness</span>
            </div>

            <!-- Divider -->
            <div class="vr bg-white opacity-25 me-4 d-none d-md-block" style="height: 60px;"></div>

            <!-- Carousel -->
            <div class="flex-grow-1 me-3">
                <p class="text-white fw-bold text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 1.5px; opacity: 0.7;">Daily Health Tip</p>
                <div id="healthTipsCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="6000">
                    <div class="carousel-inner">
                        <?php foreach ($healthTips as $index => $item): ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-white rounded-3 shadow-sm d-flex align-items-center justify-content-center flex-shrink-0 d-md-flex d-none" style="width: 44px; height: 44px;">
                                        <i class="bi <?php echo $item['icon']; ?> fs-4" style="background: -webkit-linear-gradient(135deg, #4A00E0, #8E2DE2); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-white mb-1" style="font-size: 1.05rem;"><?php echo htmlspecialchars($item['title']); ?></h6>
                                        <p class="mb-0 text-white" style="font-size: 0.88rem; line-height: 1.55; opacity: 0.92;"><?php echo htmlspecialchars($item['tip']); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#healthTipsCarousel" data-bs-slide="prev" style="width: 5%; opacity: 0;"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button>
                    <button class="carousel-control-next" type="button" data-bs-target="#healthTipsCarousel" data-bs-slide="next" style="width: 5%; opacity: 0;"><span class="carousel-control-next-icon" aria-hidden="true"></span></button>
                </div>
            </div>

            <!-- Right: Nav dots -->
            <div class="d-none d-lg-flex flex-column gap-1 me-2">
                <?php foreach ($healthTips as $index => $item): ?>
                    <button type="button" data-bs-target="#healthTipsCarousel" data-bs-slide-to="<?php echo $index; ?>"
                        style="width: 6px; height: 6px; border-radius: 50%; border: none; padding: 0; background: <?php echo $index === 0 ? 'white' : 'rgba(255,255,255,0.3)'; ?>;"></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: rgba(255,255,255,0.1);">
            <div class="h-100 bg-warning" style="animation: slideProgress 6s linear infinite;"></div>
        </div>
    </div>
</div>
<style>
    @keyframes slideProgress {
        0% { width: 0%; opacity: 1; }
        95% { width: 100%; opacity: 1; }
        100% { width: 100%; opacity: 0; }
    }
</style>
