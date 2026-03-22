<?php
// Health Tips Slideshow Component
$healthTips = [
    ["title" => "Stay Hydrated", "tip" => "Drink at least 8 glasses of water a day to maintain your body's vital functions and keep your skin healthy.", "icon" => "bi-droplet-fill", "color" => "text-info"],
    ["title" => "Balanced Diet", "tip" => "Incorporate a variety of fruits, vegetables, lean proteins, and whole grains into your daily meals.", "icon" => "bi-egg-fried", "color" => "text-warning"],
    ["title" => "Regular Exercise", "tip" => "Aim for at least 30 minutes of moderate physical activity every day to keep your heart strong.", "icon" => "bi-bicycle", "color" => "text-success"],
    ["title" => "Adequate Sleep", "tip" => "Adults should get 7-9 hours of quality sleep per night for optimal brain function and recovery.", "icon" => "bi-moon-stars-fill", "color" => "text-primary"],
    ["title" => "Mental Health", "tip" => "Take time to relax and de-stress. Practice mindfulness, meditation, or simply enjoy a quiet hobby.", "icon" => "bi-peace", "color" => "text-purple"],
    ["title" => "Wash Your Hands", "tip" => "Frequent handwashing with soap and water for 20 seconds prevents the spread of infections.", "icon" => "bi-water", "color" => "text-info"],
    ["title" => "Limit Sugar Intake", "tip" => "Reduce consumption of sugary drinks and snacks to lower the risk of diabetes and obesity.", "icon" => "bi-cup-straw", "color" => "text-danger"],
    ["title" => "Regular Check-ups", "tip" => "Don't skip your annual physical exams. Early detection is key to treating many health issues.", "icon" => "bi-clipboard2-pulse", "color" => "text-teal"],
    ["title" => "Protect Your Skin", "tip" => "Wear sunscreen outdoors to protect your skin from harmful UV rays and prevent premature aging.", "icon" => "bi-sun-fill", "color" => "text-warning"],
    ["title" => "Posture Matters", "tip" => "Sit and stand with good posture to prevent back and neck pain, especially if you work at a desk.", "icon" => "bi-person-standing", "color" => "text-success"]
];
?>

<div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden bg-primary bg-opacity-10">
    <div class="card-header bg-transparent border-0 pt-3 pb-0">
        <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-heart-pulse-fill me-2"></i>Daily Health Tips</h6>
    </div>
    <div class="card-body p-3">
        <div id="healthTipsCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-inner text-center py-2">
                <?php foreach ($healthTips as $index => $item): ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <i class="bi <?php echo $item['icon']; ?> <?php echo $item['color']; ?> display-6 mb-2 d-inline-block"></i>
                        <h6 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($item['title']); ?></h6>
                        <p class="small text-muted mb-0" style="font-size: 0.85rem;"><?php echo htmlspecialchars($item['tip']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#healthTipsCarousel" data-bs-slide="prev" style="width: 10%;">
                <span class="carousel-control-prev-icon d-none" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#healthTipsCarousel" data-bs-slide="next" style="width: 10%;">
                <span class="carousel-control-next-icon d-none" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
</div>
