USE widms;
ALTER TABLE aid_requests MODIFY status ENUM('draft','pending','approved','rejected','goods-requested','distributed') NOT NULL DEFAULT 'pending';
