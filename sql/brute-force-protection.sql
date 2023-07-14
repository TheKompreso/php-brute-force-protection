-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Июн 27 2023 г., 13:54
-- Версия сервера: 5.7.27-30
-- Версия PHP: 7.1.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `abf-ip` (
  `id` int(11) NOT NULL,
  `ip` int(11) NOT NULL,
  `try` tinyint(4) NOT NULL,
  `time` int(11) NOT NULL,
  `bantime` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `abf-users` (
  `id` int(11) NOT NULL,
  `hash` char(64) NOT NULL,
  `try` tinyint(4) NOT NULL,
  `time` int(11) NOT NULL,
  `bantime` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `abf-white` (
  `id` int(11) NOT NULL,
  `user` int(11) NOT NULL,
  `account` int(11) NOT NULL,
  `try` tinyint(4) NOT NULL,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `abf-ip`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip` (`ip`);

ALTER TABLE `abf-users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hash` (`hash`);

ALTER TABLE `abf-white`
  ADD PRIMARY KEY (`id`);


ALTER TABLE `abf-ip`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `abf-users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `abf-white`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;