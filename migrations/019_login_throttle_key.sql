-- Login throttle keys are now ip|account (was ip alone): behind a shared
-- proxy every visitor shared one bucket, so 10 bad guesses locked out all
-- logins. 191 chars fits ip(45) + sep + a 30-char name with room to spare.
ALTER TABLE login_attempts MODIFY ip VARCHAR(191) NOT NULL;
