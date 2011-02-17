<?
function android_app_badge_swedroid(&$session, $pname) {
	$path			= dirname(__FILE__);
	$badgeHeight	= 68;
	$badgeWidth		= 322;
	$fontsizeSmall 	= 9;
	$fontsizeLarge	= 12;

	$fontSmall		= $path."/fonts/DroidSans.ttf";
	$fontLarge		= $path."/fonts/DroidSans-Bold.ttf";

	$ar = new AppsRequest();
	$ar->setQuery("pname:".$pname);
	#$ar->setQuery("mixzing");
	$ar->setStartIndex(0);
	$ar->setEntriesCount(1);
	$ar->setWithExtendedInfo(true);
	#$ar->setViewType(AppsRequest_ViewType::PAID);

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

	$icon = $response->getResponsegroup(0)->getImageResponse()->getImageData();

	$img = imagecreatetruecolor($badgeWidth, $badgeHeight);

	$bg = imagecreatefrompng($path."/images/swedroid_bg.png");
	imagecopy($img, $bg, 0,0,0,0,$badgeWidth,$badgeHeight);

	$iconW = 48;
	$iconH = 48;

	$gdIcon = imagecreatefromstring($icon);
	imagecopy($img, $gdIcon, 10, 10, 0, 0, $iconW, $iconH);

	$textLeft = $iconW+15;


	$fontColor = imagecolorallocate($img, 104,104,104);

	$maxWidth = 190;
	
	#$app->setTitle("Virtual Kitchen Walkie Talkie");

	
	$fontBox = imagettfbbox($fontsizeLarge, 0, $fontLarge, $app->getTitle());
	//echo $fontBox[2];
	while($fontBox[2] > $maxWidth && $fontsizeLarge > 9) {
		$fontsizeLarge--;
		$fontBox = imagettfbbox($fontsizeLarge, 0, $fontLarge, $app->getTitle());
	}
	$nextLine = $iconH+$fontBox[5]-8;
	
	if ($fontsizeLarge < 10) $nextLine -= 2;
	
	
	$box = imagettftext($img, $fontsizeLarge, 0, $textLeft, $nextLine, $fontColor, $fontLarge, $app->getTitle());

	#echo "<xmp>".print_r($box, true)."</xmp>";
	$nextLine = $iconH-2;

	$text = $app->getCreator();
	$fontBox = imagettfbbox($fontsizeSmall, 0, $fontSmall, $text);
	imagettftext($img, $fontsizeSmall, 0, $textLeft, 55, $fontColor, $fontSmall, $text);
	$nextLine += 3;


	$starOn		= imagecreatefrompng($path."/images/star_on.png");
	$starOff	= imagecreatefrompng($path."/images/star_off.png");
	$starWidth	= imagesx($starOn);
	$starHeight = imagesy($starOn);
	$starTop	= 29;
	$rating = $app->getRating();

	for ($r = 0; $r < 5; $r++) {
		#echo $r." / ".floor($rating)."<br>";
		if ($r+1 <= floor($rating)) {
			imagecopy($img, $starOn, $textLeft+($r*($starWidth+1)), $starTop, 0, 0, $starWidth, $starHeight);
		
		} elseif ($r == floor($rating)) {
			$diff = $rating - floor($rating);
			
			imagecopy($img, $starOn, $textLeft+($r*($starWidth+1)),$starTop, 0, 0, floor($starWidth * $diff), $starHeight);
			
			$offWidth = ceil($starWidth * $diff)-1;
			imagecopy($img, $starOff, $textLeft+($r*($starWidth+1))+floor($starWidth * $diff), $starTop, $offWidth, 0, $starWidth-$offWidth, $starHeight);

		} else {
			imagecopy($img, $starOff, $textLeft+($r*($starWidth+1)), $starTop, 0, 0, $starWidth, $starHeight);
		}
		
	}

	$nextLine -= 20;

	//Add downloads count
	/*
	$text = $app->getExtendedInfo()->getDownloadsCountText()." downloads";
	$fontBox = imagettfbbox($fontsizeSmall,0, $fontSmall, $text);
	imagettftext($img, $fontsizeSmall, 0, $textLeft, $iconH+$nextLine, $fontColor, $fontSmall, $text);
	$nextLine += -$fontBox[7] + 4;

	//Add price
	if ($app->getPrice()) {
		$text = $app->getPrice();
	} else {
		$text = "Free";
	}
	$fontBox = imagettfbbox($fontSmall,0, $fontSmall, $text);
	imagettftext($img, $fontSmall, 0, $textLeft, $iconH+$nextLine, $fontColor, $fontSmall, $text);
	*/

	//Add QR Code
	$qrSize = 70;
	$chartURL = "http://chart.apis.google.com/chart?cht=qr&chs={$qrSize}x{$qrSize}&chl=market://details?id=".$app->getExtendedInfo()->getPackageName()."&chld=L|1";
	#echo $chartURL;
	$qrIcon = imagecreatefromstring(file_get_contents($chartURL));
	$crop	= 3;

	//Soften any too long texts before they reach QR code
	/*
	$area = 30;
	$start = $badgeWidth-$qrSize-5+$crop+$crop;
	for ($col = 0; $col < $area; $col++) {
		$x		= ($start - $area) +$col;
		$alpha	= floor(($area-$col) * (127 / $area));
		$color	= imagecolorallocatealpha($img, 0,0, 0,$alpha);
		imageline($img, $x, 0, $x, $badgeHeight, $color);
	}
	imagefilledrectangle($img, $start, 0, $badgeWidth, $badgeHeight, $color);
	*/


	imagecopy($img, $qrIcon, $badgeWidth-$qrSize-2+$crop+$crop, 2, $crop, $crop, $qrSize-$crop-$crop-1, $qrSize-$crop-$crop-1);
	
	ob_start();
	imagepng($img);
	imagedestroy($img);
	return ob_get_clean();
}
?>