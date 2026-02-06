<?php
/**
 * Email Header
 *
 * @author 		RestroPress
 * @package 	RestroPress/Templates/Emails
 * @version     2.1
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// This is the footer used if no others are available
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	</head>
	<body>