<?php
declare(strict_types=1);

use Enlixo\Dashboard\TutorDashboard;

test('Dashboard WhatsApp links get the Sri Lankan country code', function () {
    T::eq('94771234567', TutorDashboard::waNumber('0771234567'));
    T::eq('94771234567', TutorDashboard::waNumber('+94 77 123 4567'));
    T::eq('94771234567', TutorDashboard::waNumber('0094771234567'));
    T::eq('94771234567', TutorDashboard::waNumber('771234567'));
    T::eq('447911123456', TutorDashboard::waNumber('+44 7911 123456'));
    T::eq('', TutorDashboard::waNumber(''));
});
