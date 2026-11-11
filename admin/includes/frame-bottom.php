<?php /* Closes includes/frame-top.php and loads the page script ($ap['js']). */ ?>
                        </div>
                        <div class="flex-grow-1"></div>
                        <footer>
                            <?php include __DIR__ . '/../../includes/footer.php' ?>
                        </footer>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
    <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
    <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
    <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
    <?php foreach ($ap['scripts'] ?? [] as $src) { ?>
        <script src="<?= $apE($src) ?><?= str_starts_with($src, 'http') ? '' : '?v=' . Project::getVersion() ?>"></script>
    <?php } ?>
    <script type="module" src="<?= Project::getAssetsPath() ?>/js/enlixo/pages/<?= $apE($ap['js']) ?>?v=<?= Project::getVersion() ?>"></script>
</body>

</html>
