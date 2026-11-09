<div class="col-12">
    <div class="row g-3">
        <?php
        for ($i = 0; $i < 4; $i++) {
        ?>
            <div class="col-12 col-md-4 col-xl-3">
                <div class="tutor-class-card h-100 component-theme">

                    <!-- Image Skeleton -->
                    <div class="card-image-wrapper position-relative mb-3">
                        <div class="skeleton w-100" style="height:160px;"></div>

                        <!-- Badge Skeleton -->
                        <div class="skeleton position-absolute"
                            style="bottom:30px; left:10px; width:110px; height:25px;border-radius:20px;">
                        </div>
                    </div>

                    <!-- Card Body Skeleton -->
                    <div class="card-body-custom">

                        <!-- Subject -->
                        <div class="skeleton mb-2" style="width:90px; height:12px;"></div>

                        <!-- Class Title -->
                        <div class="skeleton mb-3" style="width:100%; height:18px;"></div>

                        <!-- Meta Row -->
                        <div class="d-flex justify-content-between mb-3">
                            <div class="skeleton" style="width:70px; height:12px;"></div>
                            <div class="skeleton" style="width:90px; height:12px;"></div>
                        </div>

                        <!-- Action Button -->
                        <div class="skeleton w-100" style="height:38px;"></div>

                    </div>
                </div>
            </div>
        <?php
        }
        ?>
    </div>
</div>