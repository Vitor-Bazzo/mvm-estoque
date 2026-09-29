-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: bdmvm
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cliente`
--

DROP TABLE IF EXISTS `cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cliente` (
  `idCliente` int(11) NOT NULL AUTO_INCREMENT,
  `nomeCliente` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` varchar(2) DEFAULT NULL,
  PRIMARY KEY (`idCliente`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cliente`
--

LOCK TABLES `cliente` WRITE;
/*!40000 ALTER TABLE `cliente` DISABLE KEYS */;
INSERT INTO `cliente` VALUES (1,'Ana Silva','ana.silva@email.com','(11) 98765-4321','Rua das Flores, 120','São Paulo','SP'),(2,'Bruno Souza','bruno.souza@email.com','(21) 97654-3210','Av. Atlântica, 450','Rio de Janeiro','RJ'),(3,'Carlos Oliveira','carlos.oliveira@email.com','(31) 96543-2109','Rua Bahia, 88','Belo Horizonte','MG'),(4,'Diana Costa','diana.costa@email.com','(41) 95432-1098','Av. Batel, 1020','Curitiba','PR'),(5,'Eduardo Santos','eduardo.santos@email.com','(51) 94321-0987','Rua dos Andradas, 310','Porto Alegre','RS'),(6,'Murilo Bahchiega Reis','murilo.b.reis@aluno.senai.br','','','',''),(7,'William Devide Komel','william.komel@docente.senai.br','','','Ourinhos','SP'),(8,'Joao Paulo','ti794adsjoao@gmail.com','','teste jao','','');
/*!40000 ALTER TABLE `cliente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fornecedor`
--

DROP TABLE IF EXISTS `fornecedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fornecedor` (
  `idFornecedor` int(11) NOT NULL AUTO_INCREMENT,
  `nomeFornecedor` varchar(255) NOT NULL,
  `segmento` varchar(100) DEFAULT NULL,
  `cnpj` varchar(18) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` varchar(2) DEFAULT NULL,
  PRIMARY KEY (`idFornecedor`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fornecedor`
--

LOCK TABLES `fornecedor` WRITE;
/*!40000 ALTER TABLE `fornecedor` DISABLE KEYS */;
INSERT INTO `fornecedor` VALUES (1,'Tech Distribuidora','Informatica','11.222.333/0001-01','vendas@techdistribuidora.com','(11) 3300-1122','Av. Paulista, 1000','São Paulo','SP'),(2,'Logitech do Brasil','Informatica','22.333.444/0001-02','suporte@logitech.com.br','(11) 4003-9000','Alameda Rio Negro, 500','Barueri','SP'),(3,'Kingston Logística','Informatica','33.444.555/0001-03','corporativo@kingston.com','(21) 2244-5566','Av. Rio Branco, 25','Rio de Janeiro','RJ'),(4,'LOréal Brasil Cosméticos','Cosméticos','44.555.666/0001-04','pedidos@loreal.com.br','(21) 3500-0011','Rua do Passeio, 38','Rio de Janeiro','RJ'),(5,'Nivea Distribuição','Cosméticos','55.666.777/0001-05','comercial@nivea.com.br','(41) 3200-4455','Av. Sete de Setembro, 200','Curitiba','PR'),(6,'Nestlé Brasil Alimentos','Alimentícios','77.888.999/0001-07','faturamento@nestle.com.br','(11) 2100-3344','Av. Guido Aliberti, 1500','São Caetano do Sul','SP'),(7,'Samsung Eletrônica','Eletro-eletrônicos','12.345.678/0001-10','faturamento@samsung.com.br','(92) 3600-7788','Av. dos Oitis, 1460','Manaus','AM');
/*!40000 ALTER TABLE `fornecedor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimento`
--

DROP TABLE IF EXISTS `movimento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimento` (
  `idMovimento` int(11) NOT NULL AUTO_INCREMENT,
  `idProduto` int(11) NOT NULL,
  `idCliente` int(11) DEFAULT NULL,
  `idFornecedor` int(11) DEFAULT NULL,
  `tipoMovimento` enum('ENTRADA','SAIDA') NOT NULL,
  `quantidade` int(11) NOT NULL,
  `observacao` varchar(255) DEFAULT NULL,
  `dataMovimento` datetime NOT NULL DEFAULT current_timestamp(),
  `dataDevolucao` datetime DEFAULT NULL,
  PRIMARY KEY (`idMovimento`),
  KEY `idProduto` (`idProduto`),
  KEY `idCliente` (`idCliente`),
  KEY `idFornecedor` (`idFornecedor`),
  CONSTRAINT `movimento_ibfk_1` FOREIGN KEY (`idProduto`) REFERENCES `produto` (`idProduto`) ON DELETE CASCADE,
  CONSTRAINT `movimento_ibfk_2` FOREIGN KEY (`idCliente`) REFERENCES `cliente` (`idCliente`) ON DELETE SET NULL,
  CONSTRAINT `movimento_ibfk_3` FOREIGN KEY (`idFornecedor`) REFERENCES `fornecedor` (`idFornecedor`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimento`
--

LOCK TABLES `movimento` WRITE;
/*!40000 ALTER TABLE `movimento` DISABLE KEYS */;
INSERT INTO `movimento` VALUES (1,1,1,NULL,'SAIDA',18,'Venda de balcão Coca-Cola','2026-09-21 14:33:13','2026-09-28 15:41:11'),(3,10,3,NULL,'SAIDA',10,'Venda Café Pilão','2026-09-21 14:33:13','2026-09-28 15:26:08'),(4,1,1,NULL,'SAIDA',22,'Venda para evento Coca-Cola','2026-09-22 14:33:13',NULL),(5,2,4,NULL,'SAIDA',12,'Venda Suco Laranja','2026-09-22 14:33:13',NULL),(6,6,5,NULL,'SAIDA',3,'Venda Teclado Redragon','2026-09-22 14:33:13','2026-09-28 15:42:02'),(7,3,1,NULL,'SAIDA',35,'Venda Água Mineral','2026-09-23 14:33:13',NULL),(8,4,2,NULL,'SAIDA',15,'Venda Energético Red Bull','2026-09-23 14:33:13',NULL),(9,7,3,NULL,'SAIDA',4,'Venda SSD Kingston','2026-09-23 14:33:13',NULL),(10,1,1,NULL,'SAIDA',30,'Venda balcão Coca-Cola','2026-09-24 14:33:13',NULL),(11,8,4,NULL,'SAIDA',6,'Venda Protetor Solar','2026-09-24 14:33:13',NULL),(12,11,5,NULL,'SAIDA',1,'Venda Smart TV Samsung','2026-09-24 14:33:13',NULL),(13,1,1,NULL,'SAIDA',25,'Venda balcão Coca-Cola','2026-09-25 14:33:13',NULL),(14,2,2,NULL,'SAIDA',16,'Venda Suco Laranja','2026-09-25 14:33:13',NULL),(15,5,3,NULL,'SAIDA',3,'Venda Mouse Logitech','2026-09-25 14:33:13','2026-09-28 14:59:29'),(16,1,1,NULL,'SAIDA',32,'Venda para restaurante','2026-09-26 14:33:13',NULL),(17,4,4,NULL,'SAIDA',20,'Venda Energético','2026-09-26 14:33:13',NULL),(18,9,5,NULL,'SAIDA',15,'Venda Creme Nivea','2026-09-26 14:33:13',NULL),(19,1,1,NULL,'SAIDA',40,'Venda atacado Coca-Cola','2026-09-27 14:33:13',NULL),(20,2,2,NULL,'SAIDA',20,'Venda Suco Laranja','2026-09-27 14:33:13',NULL),(21,7,3,NULL,'SAIDA',5,'Venda SSD Kingston','2026-09-27 14:33:13',NULL),(22,1,1,NULL,'SAIDA',28,'Venda balcão Coca-Cola hoje','2026-09-28 14:33:13','2026-09-28 15:24:31'),(23,2,4,NULL,'SAIDA',14,'Venda Suco Laranja hoje','2026-09-28 14:33:13',NULL),(24,5,5,NULL,'SAIDA',4,'Venda Mouse Logitech hoje','2026-09-28 14:33:13',NULL),(25,11,2,NULL,'SAIDA',1,'Venda Smart TV Samsung hoje','2026-09-28 14:33:13','2026-09-28 16:06:10'),(26,7,NULL,3,'ENTRADA',30,'Carga de reposição recebida','2026-09-23 14:33:13',NULL),(27,6,2,NULL,'SAIDA',1,'Venda de teste com devolução','2026-09-25 14:33:13','2026-09-26 14:33:13'),(28,3,6,3,'SAIDA',1,'teste','2026-09-28 14:56:29','2026-09-28 16:11:59'),(29,3,6,3,'SAIDA',1,'teste','2026-09-28 15:01:03','2026-09-28 15:24:20'),(30,5,7,3,'SAIDA',1,'Oi Mr. Komel','2026-09-28 15:22:24',NULL),(31,6,6,1,'SAIDA',4,'teste','2026-09-28 15:31:42',NULL),(32,6,6,1,'SAIDA',4,'teste','2026-09-28 15:32:01','2026-09-28 15:32:34'),(33,6,6,1,'SAIDA',4,'teste','2026-09-28 15:32:12','2026-09-28 16:22:05'),(34,9,6,1,'SAIDA',3,'','2026-09-28 15:32:57',NULL),(36,9,6,1,'SAIDA',3,'','2026-09-28 15:37:43',NULL),(37,10,7,3,'SAIDA',3,'1','2026-09-28 15:40:14',NULL),(38,10,6,1,'SAIDA',2,'teste','2026-09-28 15:59:02',NULL),(39,10,6,2,'SAIDA',10,'para gay como vc','2026-09-28 16:01:26',NULL),(40,3,6,1,'SAIDA',3,'','2026-09-28 16:06:24',NULL),(41,3,7,5,'ENTRADA',44,'teste','2026-09-28 16:11:09',NULL),(42,3,6,5,'SAIDA',43,'teste saida','2026-09-28 16:11:35',NULL),(43,3,6,5,'SAIDA',43,'teste saida','2026-09-28 16:11:39',NULL),(44,3,6,5,'SAIDA',43,'teste saida','2026-09-28 16:11:42',NULL),(45,3,6,5,'SAIDA',43,'teste saida','2026-09-28 16:11:46',NULL),(46,5,8,1,'SAIDA',2,'teste','2026-09-28 16:19:14',NULL);
/*!40000 ALTER TABLE `movimento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produto`
--

DROP TABLE IF EXISTS `produto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `produto` (
  `idProduto` int(11) NOT NULL AUTO_INCREMENT,
  `nomeProduto` varchar(255) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 0,
  `descricao` text DEFAULT NULL,
  `nomeFornecedor` varchar(255) DEFAULT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`idProduto`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produto`
--

LOCK TABLES `produto` WRITE;
/*!40000 ALTER TABLE `produto` DISABLE KEYS */;
INSERT INTO `produto` VALUES (1,'Refrigerante Coca-Cola 2L','Bebidas',9.50,196,'Refrigerante sabor cola tradicional em garrafa PET 2 Litros.','Nestlé Brasil Alimentos','coca_cola_2l.jpg'),(2,'Suco de Laranja Integral 1L','Bebidas',11.90,100,'Suco 100% fruta natural sem adição de açúcar ou conservantes.','Nestlé Brasil Alimentos','suco_laranja_1l.jpg'),(3,'Água Mineral Crystal 500ml','Bebidas',3.50,69,'Água mineral natural sem gás, embalagem prática.','Nestlé Brasil Alimentos','agua_mineral_500ml.jpg'),(4,'Energético Red Bull 250ml','Bebidas',10.50,90,'Bebida energética gaseificada com taurina e cafeína.','Nestlé Brasil Alimentos','red_bull_250ml.jpg'),(5,'Mouse Gamer Logitech G502 Hero','Informatica',249.90,52,'Sensor Hero 25K, 11 botões programáveis e pesos ajustáveis.','Logitech do Brasil','mouse_gamer_logitech.jpg'),(6,'Teclado Mecânico Redragon Kumara','Informatica',199.90,34,'Switch Outemu Blue, iluminação LED vermelha.','Tech Distribuidora','teclado_mecanico.jpg'),(7,'SSD Kingston A400 480GB SATA III','Informatica',289.90,80,'Velocidade de leitura até 500MB/s e gravação até 450MB/s.','Kingston Logística','ssd_kingston_a400.jpg'),(8,'Protetor Solar Anthelios FPS 70','Cosméticos',89.90,40,'Controle de oleosidade e acabamento matte.','LOréal Brasil Cosméticos','protetor_solar_anthelios.jpg'),(9,'Creme Hidratante Nivea Lata Azul 56g','Cosméticos',15.90,114,'Hidratação profunda para todos os tipos de pele.','Nivea Distribuição','creme_nivea_lata.webp'),(10,'Café Pilão Tradicional 500g','Alimentícios',18.90,90,'Café torrado e moído tradicional a vácuo.','Nestlé Brasil Alimentos','cafe_pilao_500g.jpg'),(11,'Smart TV Samsung 50\" Crystal UHD 4K','Eletro-eletrônicos',2399.00,16,'Resolução 4K UHD com processador Crystal e comando de voz.','Samsung Eletrônica','smart_tv_samsung_50.jpg');
/*!40000 ALTER TABLE `produto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario` (
  `idUsuario` int(11) NOT NULL AUTO_INCREMENT,
  `nomeUsuario` varchar(50) NOT NULL,
  `emailUsuario` varchar(255) NOT NULL,
  `senhaUsuario` varchar(255) NOT NULL,
  PRIMARY KEY (`idUsuario`),
  UNIQUE KEY `nomeUsuario` (`nomeUsuario`),
  UNIQUE KEY `emailUsuario` (`emailUsuario`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,'admin','admin@mvm.com','123');
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'bdmvm'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-28 17:02:40
