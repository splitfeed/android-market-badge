<?php
function android_app_badge_bar(&$session, $pname) {
	$path			= dirname(__FILE__);
	$badgeHeight 	= 20;
	$badgeWidth		= 350;
	$fontSizeSmall 	= 10;
	$fontSizeLarge	= 10;

	$fontLarge = $path."/fonts/DroidSans-Bold.ttf";
	$fontSmall = $path."/fonts/DroidSans.ttf";


	$ar = new AppsRequest();
	$ar->setQuery("pname:".$pname);
	$ar->setStartIndex(0);
	$ar->setEntriesCount(1);
	$ar->setWithExtendedInfo(true);

	$reqGroup = new Request_RequestGroup();
	$reqGroup->setAppsRequest($ar);
	$response = $session->execute($reqGroup);


	$app = $response->getResponsegroup(0)->getAppsResponse()->getApp(0);
	if (!$app) {
		return false;
	}

	$gir = new GetImageRequest();
	$gir->setImageUsage(GetImageRequest_AppImageUsage::ICON);
	$gir->setAppId($app->getId());

	$reqGroup = new Request_RequestGroup();
	$reqGroup->setImageRequest($gir);
	$response = $session->execute($reqGroup);

	$icon			= $response->getResponsegroup(0)->getImageResponse()->getImageData();
	$img			= imagecreatetruecolor($badgeWidth, $badgeHeight);
	$titleColor		= imagecolorallocatealpha($img, 188, 188, 196, 0);
	$priceColor		= imagecolorallocatealpha($img, 255, 255, 255, 70);
	$authorColor	= imagecolorallocatealpha($img, 255, 255, 255, 70);

	//Add background
	$bgColor		= imagecolorallocate($img, 255, 255, 255);
	imagefilledrectangle($img, 0, 0, $badgeWidth, $badgeHeight, $bgColor);

	$bg = imagecreatefrompng($path."/bar_bg_bluegray_fade3.png");
	imagecopy($img, $bg, 0, 0, 0, 0, $badgeWidth, $badgeHeight);


	//Add icon
	$iconW		= 50;
	$iconH		= 50;
	$iconPadding = floor(($badgeHeight-$iconH) / 2);

	$gdIcon		= imagecreatefromstring($icon);
	imagecopyresampled($img, $gdIcon, 0, 0, 0, 0, 20, 20, imagesx($gdIcon), imagesy($gdIcon));

	$white		= imagecolorallocate($img, 255, 255, 255);


	$textLeft	= $iconW+$iconPadding*2;


	$fontBox	= imagettfbbox($fontSizeLarge, 0, $fontLarge, $app->getTitle());
	$box		= imagettftext($img, $fontSizeLarge, 0, $textLeft, 15, $titleColor, $fontLarge, $app->getTitle());

	$nextLine = $iconH-2;


	$text = "By ".$app->getCreator();
	$fontBox = imagettfbbox($fontSizeSmall, 0, $fontSmall, $text);
	imagettftext($img, $fontSizeSmall, 0, $textLeft, 36, $authorColor, $fontSmall, $text);
	$nextLine += 3;


	$starTop	= 3;
	$starOn		= imagecreatefrompng($path."/star_on.png");
	$starOff	= imagecreatefrompng($path."/star_off.png");
	$starWidth	= imagesx($starOn);
	$starHeight	= imagesy($starOn);
	$starLeft	= $badgeWidth-(5 * ($starWidth+1))-3;
	$rating	= $app->getRating();
	for ($r = 0; $r < 5; $r++) {
		if ($r+1 <= floor($rating)) {
			imagecopy($img, $starOn, $starLeft+($r*($starWidth+1)), $starTop, 0, 0, $starWidth, $starHeight);

		} elseif ($r == floor($rating)) {
			$diff = $rating - floor($rating);

			imagecopy($img, $starOn, $starLeft+($r*($starWidth+1)), $starTop, 0, 0, floor($starWidth * $diff), $starHeight);

			$offWidth = ceil($starWidth * $diff)-1;
			imagecopy($img, $starOff, $starLeft+($r*($starWidth+1))+floor($starWidth * $diff), $starTop, $offWidth, 0, $starWidth-$offWidth, $starHeight);
		} else {
			imagecopy($img, $starOff, $starLeft+($r*($starWidth+1)), $starTop, 0, 0, $starWidth, $starHeight);
		}

	}


	$nextLine -= 20;

	ob_start();
	imagepng($img);
	imagedestroy($img);
	return ob_get_clean();
}