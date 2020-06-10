SET FOREIGN_KEY_CHECKS=0;
delete FROM `user_pipeline` where user_id = 17
delete FROM `registrations` where id = 17
delete FROM `companies` where registration_id = 17
DELETE FROM `users` where registration_id = 17

