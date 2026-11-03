<? include "config/core.php";

   // 
   if ($user_id) header('location: /orders/');


	// site setting
	$menu_name = 'sign';
	$site_set['header'] = false;
	$site_set['menu'] = false;
	$site_set['footer'] = false;
	$css = ['main'];
	$js = ['main', 'sign'];
?>
<? include "block/header.php"; ?>

	<div class="u_sign">
		<div class="bl_c">
			<div class="usign_c">

				<div class="bp_brand" style="display:flex;flex-direction:column;align-items:center;gap:10px;margin-bottom:18px"><img src="/assets/img/logo/berik-icon.svg" alt="BERIK POS" width="64" height="64" style="display:block;width:64px;height:64px"><span style="font-family:'Nunito',sans-serif;font-style:italic;font-weight:900;font-size:24px;letter-spacing:.3px;line-height:1">BERIK <b style="color:#0EA768;font-weight:800">POS</b></span></div>
				<div class="usign_head"><h3 class="usign_h"><?=t::w("Кухня", "Кухня")?></h3></div>
				<? include "block/sign_form.php"; ?>

			</div>
		</div>
	</div>

<? include "block/footer.php"; ?>