<?php
/**
 * Newsletter Campaign Email Template
 *
 * Variables available:
 * - $campaignData (array)
 * - $blocksData (array)
 * - $subscriberData (array)
 * - $unsubscribeUrl (string)
 * - $config (ProcessWire\Config)
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($campaignData['subject']); ?></title>
</head>
<body style="margin:0;padding:0;background:#f5f7fa;font-family:Arial,sans-serif;color:#1f2933;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f7fa;padding:20px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,0.08);">
                <tr>
                    <td style="padding:24px 24px 12px 24px;">
                        <h1 style="margin:0;font-size:24px;color:#0b5c2e;"><?php echo htmlspecialchars($campaignData['title']); ?></h1>
                        <?php if (!empty($campaignData['preheader'])): ?>
                            <p style="margin:8px 0 0 0;color:#7b8794;"><?php echo htmlspecialchars($campaignData['preheader']); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($campaignData['body_intro'])): ?>
                <tr>
                    <td style="padding:0 24px 12px 24px;color:#1f2933;line-height:1.5;">
                        <?php echo nl2br(htmlspecialchars($campaignData['body_intro'])); ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($blocksData as $block): ?>
                <tr>
                    <td style="padding:12px 24px 12px 24px;">
                        <table width="100%" role="presentation" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">
                            <?php if (!empty($block['image'])): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo htmlspecialchars($block['url']); ?>" target="_blank" style="text-decoration:none;">
                                        <img src="<?php echo htmlspecialchars($block['image']); ?>" alt="<?php echo htmlspecialchars($block['image_alt'] ?? $block['title']); ?>" style="display:block;width:100%;height:auto;">
                                    </a>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="padding:16px;">
                                    <p style="margin:0 0 8px 0;font-size:12px;color:#9aa5b1;text-transform:uppercase;letter-spacing:0.5px;">
                                        <?php echo strtoupper($block['type']); ?>
                                        <?php if (!empty($block['event_date'])): ?>
                                            · <?php echo date('d.m.Y', $block['event_date']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <h2 style="margin:0 0 8px 0;font-size:18px;color:#0b5c2e;"><?php echo htmlspecialchars($block['title']); ?></h2>
                                    <?php if (!empty($block['summary'])): ?>
                                        <p style="margin:0 0 12px 0;color:#1f2933;line-height:1.5;"><?php echo nl2br(htmlspecialchars($block['summary'])); ?></p>
                                    <?php endif; ?>
                                    <a href="<?php echo htmlspecialchars($block['url']); ?>" target="_blank" style="display:inline-block;padding:10px 16px;background:#0b5c2e;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">Mehr erfahren</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <?php endforeach; ?>

                <tr>
                    <td style="padding:16px 24px 24px 24px;color:#9aa5b1;font-size:12px;text-align:center;">
                        <?php if (!empty($config->email_from_name)): ?>
                            <div><?php echo htmlspecialchars($config->email_from_name); ?> · <?php echo htmlspecialchars($config->email_from ?? $config->wireMail('fromEmail')); ?></div>
                        <?php endif; ?>
                        <div style="margin-top:8px;">
                            <a href="<?php echo htmlspecialchars($unsubscribeUrl); ?>" style="color:#9aa5b1;text-decoration:underline;">Vom Newsletter abmelden</a>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
