-- 015: offer code on a tutor sign-up request (src/Platform/Promotions.php), e.g. QR25 from the landing page QR codes.
-- Additive only. Run after 014.
ALTER TABLE `requested_users`
  ADD COLUMN `promo_code` varchar(20) NULL DEFAULT NULL COMMENT 'offer code the tutor came with, see Promotions::CODES';
