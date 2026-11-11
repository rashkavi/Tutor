<?php /* The player of portal/watch-recording.php and watch-pre-recording.php (needs $kind, $recording_id, $studentId, $recordingData, $rec). */ ?>
                                    <div class="ac-card sr-player-card">
                                        <?php
                                        // Watch-time limit (includes/enlixo/recording-guard.php): when the time is used up or expired,
                                        // the player is never sent to the browser.
                                        $guardKind = $kind;
                                        $guardRecordingId = $recording_id;
                                        $guardStudentId = $studentId;
                                        $recordingGuard = require '../includes/enlixo/recording-guard.php';
                                        if (!$recordingGuard['can_play']) {
                                            echo $recordingGuard['lock_html'];
                                        } elseif (($typedPlayer = enlixo_recording_player($recordingData, $rec['title'], $recordingGuard['meter_html'])) !== null) {
                                            echo $typedPlayer;
                                        } elseif ($recordingData['integrations_integration_id'] == null) {
                                            echo '<div class="sr-legacy">' . $recordingData['recording_link'] . '</div>';
                                        } else {
                                            $bunnyData = Database::search("SELECT * FROM `video_intergration` WHERE `integrations_integration_id` = '" . (int) $recordingData['integrations_integration_id'] . "'");
                                            if ($bunnyData && $bunnyData->num_rows > 0) {
                                                $bunnyData = $bunnyData->fetch_assoc(); ?>
                                                <div class="video-wrapper">
                                                    <div class="video-loader" id="videoLoader"><div class="modern-spinner"></div></div>
                                                    <iframe id="videoIframe" src="https://iframe.mediadelivery.net/embed/<?= sl_e($bunnyData['lib_id']) ?>/<?= sl_e($recordingData['recording_link']) ?>"
                                                        style="display:none;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen></iframe>
                                                </div>
                                                <?= $recordingGuard['meter_html'] ?>
                                        <?php }
                                        } ?>
                                    </div>
