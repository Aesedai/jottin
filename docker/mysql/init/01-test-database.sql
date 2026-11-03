-- Separate database for PHPUnit, so test runs can never touch dev data.
CREATE DATABASE IF NOT EXISTS jottin_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON jottin_test.* TO 'jottin'@'%';
