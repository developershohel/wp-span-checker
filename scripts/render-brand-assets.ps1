# Renders WordPress.org and website brand PNGs for VMS Elements Form Guard.
# Usage:
#   .\scripts\render-brand-assets.ps1
#   .\scripts\render-brand-assets.ps1 -Variant Pro

param(
	[ValidateSet( 'Free', 'Pro' )]
	[string]$Variant = 'Free'
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$pluginRoot = Split-Path -Parent $PSScriptRoot
$isPro      = 'Pro' -eq $Variant
$title      = if ( $isPro ) { 'VMS Elements Form Guard Pro' } else { 'VMS Elements Form Guard' }
$tagline    = if ( $isPro ) {
	'Advanced form guards, AI summaries & licensing'
} else {
	'Email validation & spam protection for WordPress'
}
$taglineCompact = if ( $isPro ) {
	'Form guards, AI summaries & licensing'
} else {
	'Email validation & spam protection'
}
$bannerTitle = if ( $isPro ) { 'VMS Elements Form Guard' } else { $title }
$subtitle   = if ( $isPro ) {
	'Form Guard · Contact & subscribe guards · WooCommerce reviews'
} else {
	'Disposable emails · MX checks · Web Risk & VirusTotal'
}

$outDir = if ( $isPro ) {
	Join-Path ( Split-Path -Parent $pluginRoot ) 'vms-elements-form-guard-pro\assets\brand'
} else {
	Join-Path $pluginRoot 'assets\images'
}

$fontFamily = 'Segoe UI'

function New-VefgColor {
	param( [int]$R, [int]$G, [int]$B, [int]$A = 255 )
	return [System.Drawing.Color]::FromArgb( $A, $R, $G, $B )
}

function New-VefgRoundedRectPath {
	param(
		[float]$X,
		[float]$Y,
		[float]$Width,
		[float]$Height,
		[float]$Radius
	)

	$path = New-Object System.Drawing.Drawing2D.GraphicsPath
	$d    = [Math]::Min( $Radius, [Math]::Min( $Width, $Height ) / 2 )
	$path.AddArc( $X, $Y, $d * 2, $d * 2, 180, 90 )
	$path.AddArc( $X + $Width - $d * 2, $Y, $d * 2, $d * 2, 270, 90 )
	$path.AddArc( $X + $Width - $d * 2, $Y + $Height - $d * 2, $d * 2, $d * 2, 0, 90 )
	$path.AddArc( $X, $Y + $Height - $d * 2, $d * 2, $d * 2, 90, 90 )
	$path.CloseFigure()
	return $path
}

function New-VefgStringFormat {
	param(
		[System.Drawing.StringAlignment]$Align = [System.Drawing.StringAlignment]::Near,
		[System.Drawing.StringAlignment]$LineAlign = [System.Drawing.StringAlignment]::Near,
		[bool]$Wrap = $true
	)

	$sf = New-Object System.Drawing.StringFormat
	$sf.Alignment     = $Align
	$sf.LineAlignment = $LineAlign
	$sf.Trimming      = [System.Drawing.StringTrimming]::Word
	if ( $Wrap ) {
		$sf.FormatFlags = [System.Drawing.StringFormatFlags]::LineLimit
	}
	return $sf
}

function Measure-VefgTextBlock {
	param(
		[System.Drawing.Graphics]$G,
		[string]$Text,
		[System.Drawing.Font]$Font,
		[float]$MaxWidth
	)

	$sf = New-VefgStringFormat
	$size = $G.MeasureString( $Text, $Font, [int][Math]::Ceiling( $MaxWidth ), $sf )
	$sf.Dispose()
	return $size
}

function New-VefgFittedFont {
	param(
		[System.Drawing.Graphics]$G,
		[string]$Text,
		[string]$Family,
		[System.Drawing.FontStyle]$Style,
		[float]$MaxWidth,
		[float]$StartSize,
		[float]$MinSize = 11
	)

	$size = $StartSize
	while ( $size -ge $MinSize ) {
		$font = [System.Drawing.Font]::new( $Family, $size, $Style, [System.Drawing.GraphicsUnit]::Pixel )
		$measured = Measure-VefgTextBlock $G $Text $font $MaxWidth
		if ( $measured.Width -le ( $MaxWidth + 2 ) ) {
			return $font
		}
		$font.Dispose()
		$size -= 1
	}

	return [System.Drawing.Font]::new( $Family, $MinSize, $Style, [System.Drawing.GraphicsUnit]::Pixel )
}

function Draw-VefgTextBlock {
	param(
		[System.Drawing.Graphics]$G,
		[string]$Text,
		[System.Drawing.Font]$Font,
		[System.Drawing.Brush]$Brush,
		[System.Drawing.RectangleF]$Rect
	)

	$sf = New-VefgStringFormat
	$G.DrawString( $Text, $Font, $Brush, $Rect, $sf )
	$sf.Dispose()
}

function Draw-VefgLogoMark {
	param(
		[System.Drawing.Graphics]$G,
		[System.Drawing.RectangleF]$Bounds,
		[float]$CornerRatio = 0.227
	)

	$g = $G
	$g.SmoothingMode     = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
	$g.PixelOffsetMode   = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
	$g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic

	$radius = $Bounds.Width * $CornerRatio
	$rect   = $Bounds

	$bgBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
		$rect,
		( New-VefgColor -R 79 -G 70 -B 229 ),
		( New-VefgColor -R 99 -G 102 -B 241 ),
		45
	)
	$rounded = New-VefgRoundedRectPath $rect.X $rect.Y $rect.Width $rect.Height $radius
	$g.FillPath( $bgBrush, $rounded )

	$shineRect = [System.Drawing.RectangleF]::new( $rect.X, $rect.Y, $rect.Width, $rect.Height * 0.55 )
	$shineBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
		$shineRect,
		( New-VefgColor -R 255 -G 255 -B 255 -A 56 ),
		( New-VefgColor -R 255 -G 255 -B 255 -A 0 ),
		90
	)
	$g.FillPath( $shineBrush, $rounded )

	$cx = $rect.X + $rect.Width / 2
	$cy = $rect.Y + $rect.Height / 2
	$sw = $rect.Width * 0.58
	$sh = $rect.Height * 0.62

	$shield = New-Object System.Drawing.Drawing2D.GraphicsPath
	$shield.AddBezier(
		$cx, $rect.Y + $rect.Height * 0.2,
		$rect.X + $rect.Width * 0.88, $rect.Y + $rect.Height * 0.32,
		$rect.X + $rect.Width * 0.88, $rect.Y + $rect.Height * 0.58,
		$cx, $rect.Y + $rect.Height * 0.84
	)
	$shield.AddBezier(
		$cx, $rect.Y + $rect.Height * 0.84,
		$rect.X + $rect.Width * 0.12, $rect.Y + $rect.Height * 0.58,
		$rect.X + $rect.Width * 0.12, $rect.Y + $rect.Height * 0.32,
		$cx, $rect.Y + $rect.Height * 0.2
	)
	$shield.CloseFigure()

	$g.FillPath( ( New-Object System.Drawing.SolidBrush ( New-VefgColor -R 255 -G 255 -B 255 -A 245 ) ), $shield )

	$pen = New-Object System.Drawing.Pen ( ( New-VefgColor -R 6 -G 182 -B 212 ) ), ( $rect.Width * 0.05 )
	$pen.StartCap  = [System.Drawing.Drawing2D.LineCap]::Round
	$pen.EndCap    = [System.Drawing.Drawing2D.LineCap]::Round
	$pen.LineJoin  = [System.Drawing.Drawing2D.LineJoin]::Round

	$check = New-Object System.Drawing.Drawing2D.GraphicsPath
	$check.AddLines(
		@(
			[System.Drawing.PointF]::new( $cx - $sw * 0.22, $cy + $sh * 0.02 ),
			[System.Drawing.PointF]::new( $cx - $sw * 0.02, $cy + $sh * 0.2 ),
			[System.Drawing.PointF]::new( $cx + $sw * 0.28, $cy - $sh * 0.18 )
		)
	)
	$g.DrawPath( $pen, $check )

	if ( $isPro ) {
		$badgeRect = [System.Drawing.RectangleF]::new(
			$rect.Right - $rect.Width * 0.34,
			$rect.Bottom - $rect.Height * 0.22,
			$rect.Width * 0.3,
			$rect.Height * 0.16
		)
		$badgePath = New-VefgRoundedRectPath $badgeRect.X $badgeRect.Y $badgeRect.Width $badgeRect.Height ( $badgeRect.Height * 0.35 )
		$g.FillPath( ( New-Object System.Drawing.SolidBrush ( New-VefgColor -R 245 -G 158 -B 11 ) ), $badgePath )
		$badgeFont = [System.Drawing.Font]::new( $fontFamily, [float]( $rect.Width * 0.09 ), [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel )
		$sf        = New-Object System.Drawing.StringFormat
		$sf.Alignment     = [System.Drawing.StringAlignment]::Center
		$sf.LineAlignment = [System.Drawing.StringAlignment]::Center
		$g.DrawString( 'PRO', $badgeFont, [System.Drawing.Brushes]::White, $badgeRect, $sf )
		$badgeFont.Dispose()
		$sf.Dispose()
		$badgePath.Dispose()
	}

	$bgBrush.Dispose()
	$shineBrush.Dispose()
	$rounded.Dispose()
	$shield.Dispose()
	$pen.Dispose()
	$check.Dispose()
}

function Save-VefgBitmap {
	param(
		[System.Drawing.Bitmap]$Bitmap,
		[string]$Path
	)

	$dir = Split-Path -Parent $Path
	if ( -not ( Test-Path $dir ) ) {
		New-Item -ItemType Directory -Path $dir -Force | Out-Null
	}

	$Bitmap.Save( $Path, [System.Drawing.Imaging.ImageFormat]::Png )
}

function New-VefgIconBitmap {
	param( [int]$Size )

	$bmp = New-Object System.Drawing.Bitmap $Size, $Size
	$g   = [System.Drawing.Graphics]::FromImage( $bmp )
	$g.Clear( [System.Drawing.Color]::Transparent )
	Draw-VefgLogoMark $g ( [System.Drawing.RectangleF]::new( 0, 0, $Size, $Size ) )
	$g.Dispose()
	return $bmp
}

function Draw-VefgBannerBackground {
	param(
		[System.Drawing.Graphics]$G,
		[int]$Width,
		[int]$Height
	)

	$rect = [System.Drawing.Rectangle]::new( 0, 0, $Width, $Height )
	$bg   = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
		$rect,
		( New-VefgColor -R 49 -G 46 -B 129 ),
		( New-VefgColor -R 99 -G 102 -B 241 ),
		35
	)
	$G.FillRectangle( $bg, $rect )

	$blobBrush = New-Object System.Drawing.SolidBrush ( New-VefgColor -R 6 -G 182 -B 212 -A 40 )
	$G.FillEllipse( $blobBrush, ( $Width * 0.72 ), ( -$Height * 0.15 ), ( $Width * 0.35 ), ( $Height * 0.7 ) )
	$G.FillEllipse( $blobBrush, ( -$Width * 0.08 ), ( $Height * 0.55 ), ( $Width * 0.22 ), ( $Height * 0.55 ) )

	$bg.Dispose()
	$blobBrush.Dispose()
}

function New-VefgBannerBitmap {
	param( [int]$Width, [int]$Height )

	$bmp = New-Object System.Drawing.Bitmap $Width, $Height
	$g   = [System.Drawing.Graphics]::FromImage( $bmp )
	$g.SmoothingMode     = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
	$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

	Draw-VefgBannerBackground $g $Width $Height

	$padX     = [int]( $Width * 0.04 )
	if ( $padX -lt 16 ) { $padX = 16 }
	$padY     = [int]( $Height * 0.1 )
	if ( $padY -lt 12 ) { $padY = 12 }
	$compact  = $Height -le 280

	if ( $compact ) {
		$padX = 18
		$padY = 14
	}

	$logoSize = [int]( [Math]::Min( $Height * 0.68, $Width * 0.14 ) )
	if ( $compact ) {
		$logoSize = [int]( [Math]::Min( $Height * 0.5, 68 ) )
	}
	$logoX    = $padX
	$logoY    = [int]( ( $Height - $logoSize ) / 2 )
	Draw-VefgLogoMark $g ( [System.Drawing.RectangleF]::new( $logoX, $logoY, $logoSize, $logoSize ) )

	$gap      = [int]( $Width * 0.025 )
	if ( $gap -lt 10 ) { $gap = 10 }
	if ( $compact ) { $gap = 12 }
	$textX    = $logoX + $logoSize + $gap
	$textW    = $Width - $textX - $padX

	$bannerTagline = if ( $compact ) { $taglineCompact } else { $tagline }

	$titleMin   = if ( $compact ) { 15 } else { 22 }
	$titleMax   = if ( $compact ) { [float]( $Height * 0.19 ) } else { [float]( $Height * 0.16 ) }
	if ( $titleMax -lt $titleMin ) { $titleMax = $titleMin }

	$titleFont = New-VefgFittedFont -G $g -Text $bannerTitle -Family $fontFamily -Style ([System.Drawing.FontStyle]::Bold) -MaxWidth $textW -StartSize $titleMax -MinSize $titleMin
	$brushTitle = New-Object System.Drawing.SolidBrush ( New-VefgColor -R 255 -G 255 -B 255 )
	$titleLineH = Measure-VefgTextBlock $g $bannerTitle $titleFont $textW

	$tagMax   = if ( $compact ) { [float]( $Height * 0.1 ) } else { [float]( $Height * 0.09 ) }
	$tagMin   = if ( $compact ) { 11 } else { 13 }
	if ( $tagMax -lt $tagMin ) { $tagMax = $tagMin }

	$tagFont = New-VefgFittedFont -G $g -Text $bannerTagline -Family $fontFamily -Style ([System.Drawing.FontStyle]::Regular) -MaxWidth $textW -StartSize $tagMax -MinSize $tagMin
	$brushSub = New-Object System.Drawing.SolidBrush ( New-VefgColor -R 199 -G 210 -B 254 )
	$tagLineH = Measure-VefgTextBlock $g $bannerTagline $tagFont $textW

	$lineGap = if ( $compact ) { 4 } else { ( $Height * 0.035 ) }
	$blockH  = $titleLineH.Height + $lineGap + $tagLineH.Height

	$metaLineH = $null
	$metaFont  = $null
	$brushMeta = $null
	if ( -not $compact ) {
		$metaMax   = [float]( $Height * 0.07 )
		$metaMin   = 11
		if ( $metaMax -lt $metaMin ) { $metaMax = $metaMin }
		$metaFont  = New-VefgFittedFont -G $g -Text $subtitle -Family $fontFamily -Style ([System.Drawing.FontStyle]::Regular) -MaxWidth $textW -StartSize $metaMax -MinSize $metaMin
		$brushMeta = New-Object System.Drawing.SolidBrush ( New-VefgColor -R 165 -G 180 -B 252 )
		$metaLineH = Measure-VefgTextBlock $g $subtitle $metaFont $textW
		$blockH += ( $Height * 0.025 ) + $metaLineH.Height
	}

	$blockY = [float]( ( $Height - $blockH ) / 2 )
	if ( $blockY -lt $padY ) { $blockY = $padY }

	$titleRect = [System.Drawing.RectangleF]::new( $textX, $blockY, $textW, $titleLineH.Height + 2 )
	Draw-VefgTextBlock $g $bannerTitle $titleFont $brushTitle $titleRect

	$tagY = $titleRect.Bottom + $lineGap
	$tagRect = [System.Drawing.RectangleF]::new( $textX, $tagY, $textW, $tagLineH.Height + 2 )
	Draw-VefgTextBlock $g $bannerTagline $tagFont $brushSub $tagRect

	if ( -not $compact ) {
		$metaY = $tagRect.Bottom + ( $Height * 0.025 )
		$metaRect = [System.Drawing.RectangleF]::new( $textX, $metaY, $textW, $metaLineH.Height + 2 )
		Draw-VefgTextBlock $g $subtitle $metaFont $brushMeta $metaRect
		$metaFont.Dispose()
		$brushMeta.Dispose()
	}

	$titleFont.Dispose()
	$tagFont.Dispose()
	$brushTitle.Dispose()
	$brushSub.Dispose()
	$g.Dispose()
	return $bmp
}

function Draw-VefgAdminChrome {
	param(
		[System.Drawing.Graphics]$G,
		[int]$Width,
		[int]$Height,
		[string]$PageTitle
	)

	$g = $G
	$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias

	$g.Clear( ( New-VefgColor -R 240 -G 242 -B 245 ) )

	$barRect = [System.Drawing.Rectangle]::new( 0, 0, $Width, 48 )
	$barBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
		$barRect,
		( New-VefgColor -R 49 -G 46 -B 129 ),
		( New-VefgColor -R 79 -G 70 -B 229 ),
		0
	)
	$g.FillRectangle( $barBrush, $barRect )
	$barBrush.Dispose()

	$sideW = [int]( $Width * 0.19 )
	$sideRect = [System.Drawing.Rectangle]::new( 0, 48, $sideW, $Height - 48 )
	$g.FillRectangle( ( New-Object System.Drawing.SolidBrush ( New-VefgColor -R 30 -G 27 -B 75 ) ), $sideRect )

	$navY = 72
	$navItems = @( 'Dashboard', 'Form Guard', 'Domains', 'API Settings', 'AI Settings', 'Activity' )
	foreach ( $item in $navItems ) {
		$isActive = $item -eq $PageTitle
		$navBrush = if ( $isActive ) {
			New-Object System.Drawing.SolidBrush ( New-VefgColor -R 79 -G 70 -B 229 )
		} else {
			New-Object System.Drawing.SolidBrush ( New-VefgColor -R 55 -G 48 -B 120 )
		}
		$navRect = [System.Drawing.Rectangle]::new( 12, $navY, $sideW - 24, 34 )
		$g.FillRectangle( $navBrush, $navRect )
		$navFont = [System.Drawing.Font]::new( $fontFamily, 12, [System.Drawing.FontStyle]::Regular, [System.Drawing.GraphicsUnit]::Pixel )
		$g.DrawString( $item, $navFont, [System.Drawing.Brushes]::White, 22, $navY + 8 )
		$navFont.Dispose()
		$navBrush.Dispose()
		$navY += 42
	}

	$contentX = $sideW + 28
	$contentY = 72
	$contentW = $Width - $contentX - 28
	$contentH = $Height - $contentY - 28

	$cardRect = [System.Drawing.Rectangle]::new( $contentX, $contentY, $contentW, $contentH )
	$g.FillRectangle( [System.Drawing.Brushes]::White, $cardRect )
	$cardPen = New-Object System.Drawing.Pen ( ( New-VefgColor -R 226 -G 232 -B 240 ) )
	$g.DrawRectangle( $cardPen, $cardRect )
	$cardPen.Dispose()

	$titleFont = [System.Drawing.Font]::new( $fontFamily, 22, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel )
	$g.DrawString( $PageTitle, $titleFont, ( New-Object System.Drawing.SolidBrush ( New-VefgColor -R 30 -G 27 -B 75 ) ), $contentX + 24, $contentY + 20 )
	$titleFont.Dispose()
}

function New-VefgScreenshotBitmap {
	param(
		[string]$PageTitle,
		[string]$Description,
		[string]$Detail
	)

	$width  = 1280
	$height = 720
	$bmp    = New-Object System.Drawing.Bitmap $width, $height
	$g      = [System.Drawing.Graphics]::FromImage( $bmp )
	$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

	Draw-VefgAdminChrome $g $width $height $PageTitle

	$sideW    = [int]( $width * 0.19 )
	$contentX = $sideW + 52
	$contentY = 132
	$descFont = [System.Drawing.Font]::new( $fontFamily, 14, [System.Drawing.FontStyle]::Regular, [System.Drawing.GraphicsUnit]::Pixel )
	$detailFont = [System.Drawing.Font]::new( $fontFamily, 12, [System.Drawing.FontStyle]::Regular, [System.Drawing.GraphicsUnit]::Pixel )
	$descBrush = New-Object System.Drawing.SolidBrush ( New-VefgColor -R 100 -G 116 -B 139 )
	$g.DrawString( $Description, $descFont, $descBrush, $contentX, $contentY )

	$panelY = $contentY + 36
	$panelH = 88
	$panelW = ( $width - $contentX - 52 )
	$panelBrush = New-Object System.Drawing.SolidBrush ( New-VefgColor -R 248 -G 250 -B 252 )
	$panelPen   = New-Object System.Drawing.Pen ( ( New-VefgColor -R 226 -G 232 -B 240 ) )

	for ( $i = 0; $i -lt 3; $i++ ) {
		$py = $panelY + ( $i * ( $panelH + 14 ) )
		$panelRect = [System.Drawing.Rectangle]::new( $contentX, $py, $panelW, $panelH )
		$g.FillRectangle( $panelBrush, $panelRect )
		$g.DrawRectangle( $panelPen, $panelRect )

		$accent = New-Object System.Drawing.SolidBrush ( New-VefgColor -R 79 -G 70 -B 229 )
		$g.FillRectangle( $accent, [System.Drawing.Rectangle]::new( $contentX + 16, $py + 20, 6, 48 ) )
		$accent.Dispose()

		$line1 = if ( $i -eq 0 ) { $Detail } else { 'Validation rules, mappings, and protection status' }
		$line2 = if ( $i -eq 0 ) { 'Real-time checks before form submission' } else { 'Configure guards and review blocked attempts' }
		$g.DrawString( $line1, $detailFont, $descBrush, $contentX + 32, $py + 22 )
		$g.DrawString( $line2, $detailFont, $descBrush, $contentX + 32, $py + 46 )
	}

	$descFont.Dispose()
	$detailFont.Dispose()
	$descBrush.Dispose()
	$panelBrush.Dispose()
	$panelPen.Dispose()
	$g.Dispose()
	return $bmp
}

$screenshotDefs = @(
	@{ Page = 'Dashboard'; Desc = 'Overview of spam protection statistics and quick links'; Detail = 'Blocked today: 24 · Validations: 1,284 · Success rate: 98.2%' },
	@{ Page = 'Form Guard'; Desc = 'Configure form mappings with flexible targeting options'; Detail = 'Map Contact Form 7, WPForms, WooCommerce, and custom HTML forms' },
	@{ Page = 'Form Guard'; Desc = 'Detailed validation options for each mapped form'; Detail = 'MX checks, disposable lists, HTTPS, and reCAPTCHA per form' },
	@{ Page = 'Domains'; Desc = 'Manage trusted domains that always pass validation'; Detail = 'Whitelist partners, internal domains, and approved senders' },
	@{ Page = 'Domains'; Desc = 'View and add blocked disposable email domains'; Detail = '10,000+ disposable domains with custom blocklist support' },
	@{ Page = 'API Settings'; Desc = 'Configure Google Web Risk, VirusTotal, and reCAPTCHA'; Detail = 'Enterprise malware scanning and bot protection keys' },
	@{ Page = 'AI Settings'; Desc = 'Configure AI providers for spam detection and summaries'; Detail = 'OpenAI, Anthropic, Gemini, and DeepSeek integration' },
	@{ Page = 'Comment Guard'; Desc = 'Advanced comment spam rules and patterns'; Detail = 'Custom rules, AI analysis, and comment blocking scopes' },
	@{ Page = 'Activity'; Desc = 'Detailed logging of all validation events'; Detail = 'Filter by form, domain, result, and time range' },
	@{ Page = 'Form Guard'; Desc = 'Real-time validation feedback on frontend forms'; Detail = 'Inline errors and validation button before submit' }
)

New-Item -ItemType Directory -Path $outDir -Force | Out-Null

function Clear-VefgStaleBrandFiles {
	param( [string]$Dir, [bool]$IncludeScreenshots )

	$keep = @(
		'banner-772x250.png',
		'banner-1544x500.png',
		'icon-128x128.png',
		'icon-256x256.png',
		'icon.svg'
	)
	if ( $IncludeScreenshots ) {
		$keep += 1..10 | ForEach-Object { 'screenshot-{0}.png' -f $_ }
	}

	if ( -not ( Test-Path $Dir ) ) {
		return
	}

	Get-ChildItem -Path $Dir -File | Where-Object {
		$keep -notcontains $_.Name
	} | Remove-Item -Force
}

if ( $isPro ) {
	Clear-VefgStaleBrandFiles -Dir $outDir -IncludeScreenshots $false
} else {
	Clear-VefgStaleBrandFiles -Dir $outDir -IncludeScreenshots $true
}

$icon256 = New-VefgIconBitmap 256
$icon128 = New-VefgIconBitmap 128
Save-VefgBitmap $icon256 ( Join-Path $outDir 'icon-256x256.png' )
Save-VefgBitmap $icon128 ( Join-Path $outDir 'icon-128x128.png' )

$banner1544 = New-VefgBannerBitmap 1544 500
$banner772  = New-VefgBannerBitmap 772 250
Save-VefgBitmap $banner1544 ( Join-Path $outDir 'banner-1544x500.png' )
Save-VefgBitmap $banner772 ( Join-Path $outDir 'banner-772x250.png' )

if ( -not $isPro ) {
	$idx = 1
	foreach ( $shot in $screenshotDefs ) {
		$shotBmp = New-VefgScreenshotBitmap $shot.Page $shot.Desc $shot.Detail
		Save-VefgBitmap $shotBmp ( Join-Path $outDir ( 'screenshot-{0}.png' -f $idx ) )
		$shotBmp.Dispose()
		$idx++
	}
}

$iconSvgSrc = Join-Path $pluginRoot 'assets\brand\logo.svg'
if ( Test-Path $iconSvgSrc ) {
	Copy-Item -Path $iconSvgSrc -Destination ( Join-Path $outDir 'icon.svg' ) -Force
}

$icon256.Dispose()
$icon128.Dispose()
$banner1544.Dispose()
$banner772.Dispose()

Write-Host "Rendered $Variant brand assets -> $outDir"
