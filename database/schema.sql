-- MovieBook Database Schema
-- Run this in phpMyAdmin or MySQL command line

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS moviebook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE moviebook;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin', 'theater') DEFAULT 'user',
    profile_pic VARCHAR(255) DEFAULT NULL,
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Movies table
CREATE TABLE IF NOT EXISTS movies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    poster_url VARCHAR(500),
    backdrop_url VARCHAR(500),
    director VARCHAR(100),
    genre VARCHAR(100),
    runtime INT COMMENT 'Duration in minutes',
    rating DECIMAL(3,1) DEFAULT 0.0,
    release_date DATE,
    status ENUM('now_showing', 'coming_soon', 'ended') DEFAULT 'now_showing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- User watchlist
CREATE TABLE IF NOT EXISTS watchlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    movie_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_watchlist (user_id, movie_id)
) ENGINE=InnoDB;

-- User diary (watched movies)
CREATE TABLE IF NOT EXISTS diary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    movie_id INT NOT NULL,
    watched_date DATE NOT NULL,
    rating DECIMAL(2,1) DEFAULT NULL COMMENT 'User rating 0.5 to 5.0',
    liked BOOLEAN DEFAULT FALSE,
    rewatch BOOLEAN DEFAULT FALSE,
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- User favorites
CREATE TABLE IF NOT EXISTS favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    movie_id INT NOT NULL,
    position INT DEFAULT 0 COMMENT 'Order in favorites list',
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, movie_id)
) ENGINE=InnoDB;

-- User lists
CREATE TABLE IF NOT EXISTS user_lists (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- List items
CREATE TABLE IF NOT EXISTS list_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    list_id INT NOT NULL,
    movie_id INT NOT NULL,
    position INT DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (list_id) REFERENCES user_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_list_item (list_id, movie_id)
) ENGINE=InnoDB;

-- Theaters
CREATE TABLE IF NOT EXISTS theaters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    owner_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    city VARCHAR(100),
    total_seats INT DEFAULT 100,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Showtimes
CREATE TABLE IF NOT EXISTS showtimes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    theater_id INT NOT NULL,
    movie_id INT NOT NULL,
    show_date DATE NOT NULL,
    show_time TIME NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    available_seats INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (theater_id) REFERENCES theaters(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Bookings
CREATE TABLE IF NOT EXISTS bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    showtime_id INT NOT NULL,
    seats VARCHAR(255) NOT NULL COMMENT 'Comma separated seat numbers',
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    booking_code VARCHAR(20) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (showtime_id) REFERENCES showtimes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insert demo users (password is: sa416208)
-- The hash below is for 'sa416208' using password_hash with PASSWORD_DEFAULT
-- Both emails can login as user, admin, and theater
INSERT INTO users (name, email, phone, password, role) VALUES 
('Yash Patil', 'yashpatil@gmail.com', '9876543210', '$2y$10$35M8GrNK.sc7eMKtUPH8Y.KnOvRisCiLuCCcP9IegaQkTGtfSwrlK', 'user'),
('Yash Admin', 'yashpatil@gmail.com', '9876543210', '$2y$10$35M8GrNK.sc7eMKtUPH8Y.KnOvRisCiLuCCcP9IegaQkTGtfSwrlK', 'admin'),
('Yash Theater', 'yashpatil@gmail.com', '9876543210', '$2y$10$35M8GrNK.sc7eMKtUPH8Y.KnOvRisCiLuCCcP9IegaQkTGtfSwrlK', 'theater'),
('Sanket Patil', 'sanket4712@gmail.com', '9876543211', '$2y$10$35M8GrNK.sc7eMKtUPH8Y.KnOvRisCiLuCCcP9IegaQkTGtfSwrlK', 'user'),
('Sanket Admin', 'sanket4712@gmail.com', '9876543211', '$2y$10$35M8GrNK.sc7eMKtUPH8Y.KnOvRisCiLuCCcP9IegaQkTGtfSwrlK', 'admin'),
('Sanket Theater', 'sanket4712@gmail.com', '9876543211', '$2y$10$35M8GrNK.sc7eMKtUPH8Y.KnOvRisCiLuCCcP9IegaQkTGtfSwrlK', 'theater');

-- Insert some sample movies
INSERT INTO movies (title, description, poster_url, director, genre, runtime, rating, release_date, status) VALUES
('The Dark Knight', 'When the menace known as the Joker wreaks havoc and chaos on the people of Gotham, Batman must accept one of the greatest psychological and physical tests of his ability to fight injustice.', 'https://m.media-amazon.com/images/I/71L6iHkVMoL._AC_UF894,1000_QL80_.jpg', 'Christopher Nolan', 'Action, Crime, Drama', 152, 9.0, '2008-07-18', 'now_showing'),
('Inception', 'A thief who steals corporate secrets through the use of dream-sharing technology is given the inverse task of planting an idea into the mind of a C.E.O.', 'https://m.media-amazon.com/images/I/71wBRrJWwIL._AC_UF894,1000_QL80_.jpg', 'Christopher Nolan', 'Action, Adventure, Sci-Fi', 148, 8.8, '2010-07-16', 'now_showing'),
('Interstellar', 'A team of explorers travel through a wormhole in space in an attempt to ensure humanity''s survival.', 'https://m.media-amazon.com/images/M/MV5BNWIwODRlZTUtY2U3ZS00Yzg1LWJhNzYtMmZiYmEyNjU1NjMzXkEyXkFqcGc@._V1_.jpg', 'Christopher Nolan', 'Adventure, Drama, Sci-Fi', 169, 8.7, '2014-11-07', 'now_showing'),
('Dune: Part Two', 'Paul Atreides unites with Chani and the Fremen while seeking revenge against the conspirators who destroyed his family.', 'https://m.media-amazon.com/images/M/MV5BN2JkMDc5MGQtZjg3YS00NmFiLWIyZmQtZTJmNTM5MjVmYTQ4XkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg', 'Denis Villeneuve', 'Action, Adventure, Drama', 166, 8.5, '2024-03-01', 'now_showing'),
('Oppenheimer', 'The story of American scientist J. Robert Oppenheimer and his role in the development of the atomic bomb.', 'https://m.media-amazon.com/images/M/MV5BMTc5MDE2ODcwNV5BMl5BanBnXkFtZTgwMzI2NzQ2NzM@._V1_FMjpg_UX1000_.jpg', 'Christopher Nolan', 'Biography, Drama, History', 180, 8.4, '2023-07-21', 'now_showing')
ON DUPLICATE KEY UPDATE title = VALUES(title);
