<?php
/**
 * Email Footer
 *
 * @author 		RestroPress
 * @package 	RestroPress/Templates/Emails
 * @version     2.1
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// For gmail compatibility, including CSS styles in head/body are stripped out therefore styles need to be inline. These variables contain rules which are added to the template inline.
$template_footer = "
	border-top:0;
	-webkit-border-radius:3px;
";
$credit = "
	border:0;
	color: #000000;
	font-family: 'Helvetica Neue', Helvetica, Arial, 'Lucida Grande', sans-serif;
	font-size:12px;
	line-height:125%;
	text-align:center;
";
?>
															</div>
														</td>
                                                    </tr>
                                                </table>
                                                <!-- End Content -->
                                            </td>
                                        </tr>
                                    </table>
                                    <!-- End Body -->
                                </td>
                            </tr>
                            <tr>
                                <td align="center" valign="top">
                                    <!-- Footer -->
                                    <table border="0" cellpadding="10" cellspacing="0" width="600" id="template_footer" style="<?php echo wp_kses_post( $template_footer ) ; ?>">
                                        <tr>
                                            <td valign="top">
                                                <table border="0" cellpadding="10" cellspacing="0" width="100%">
                                                    <tr>
                                                        <td colspan="2" valign="middle" id="credit" style="<?php echo wp_kses_post( $credit ); ?>">
                                                           <?php 
                                                            $page = rpress_get_option( 'order_history_page', '' );
                                                           if( is_user_logged_in()) { 
                                                           echo wpautop( wp_kses_post( wptexturize( apply_filters( 'rpress_email_footer_text', '<a href="' . esc_url( get_permalink(  $page ) ) . '">' . 'View Details' . '</a>' ) ) ) ); 
                                                            }
                                                           ?>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <!-- End Footer -->
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </body>
</html>