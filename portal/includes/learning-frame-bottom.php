<?php /* end of the frame opened by learning-frame-top.php: footer, shared scripts, $lf['icons'] and $lf['js'] */ ?>
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

    <?= $lf['icons'] ? sl_icons_script($lf['icons']) : '' ?>
    <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
    <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
    <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
    <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
    <?php foreach ($lf['js'] as $src) { ?>
        <script src="<?= sl_e($src) ?>?v=<?= Project::getVersion() ?>"></script>
    <?php } ?>
    <?php foreach ($lf['modules'] ?? [] as $src) { ?>
        <script type="module" src="<?= sl_e($src) ?>?v=<?= Project::getVersion() ?>"></script>
    <?php } ?>
</body>

</html>
