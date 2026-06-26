
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Offorest cảnh báo API hết tiền' }}</title>
</head>
<body style="margin:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#061044;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:760px;background:#ffffff;border-radius:18px;box-shadow:0 18px 50px rgba(15,23,42,.08);overflow:hidden;">
                    <tr>
                        <td style="padding:34px 38px 18px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="left" style="width:150px;">
                                        <img src="{{ asset('images/offorest-logo.jpg') }}" alt="Offorest" width="96" style="display:block;width:96px;height:auto;object-fit:contain;">
                                        <div style="margin-top:8px;font-size:19px;font-weight:800;color:#07115d;">Offorest</div>
                                    </td>
                                    <td align="center">
                                        <div style="display:inline-block;background:#d50b0b;color:#ffffff;border-radius:10px;padding:13px 28px;font-size:25px;font-weight:900;letter-spacing:.5px;box-shadow:0 6px 14px rgba(213,11,11,.18);">
                                            THÔNG BÁO
                                        </div>
                                    </td>
                                    <td align="right" style="width:150px;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:84px 50px 28px;">
                            <div style="position:relative;border:1px solid #b7c1e3;border-radius:12px;padding:72px 54px 48px;">
                                <div style="margin:-112px auto 46px;max-width:520px;background:#06145c;color:#ffffff;border-radius:10px;padding:18px 24px;text-align:center;font-size:25px;font-weight:900;letter-spacing:.3px;box-shadow:0 8px 18px rgba(6,20,92,.18);">
                                    CẢNH BÁO QUOTA HẾT
                                </div>

                                <div style="text-align:center;margin-bottom:38px;">
                                    <div style="font-size:27px;line-height:1.35;color:#061044;">
                                        Xin chào <span style="color:#c40a0a;font-weight:900;">{{ $userName ?? 'User' }}</span>,
                                    </div>
                                    <div style="margin-top:10px;font-size:22px;line-height:1.45;color:#061044;">
                                        Tài khoản <span style="font-weight:900;color:#c40a0a;">{{ $accountName ?? 'API' }}</span> hết tiền hoặc quota.
                                    </div>
                                </div>

                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td style="width:130px;padding:18px 0;font-size:22px;font-weight:700;color:#06145c;">Trang web:</td>
                                        <td style="padding:18px 0;font-size:24px;font-weight:900;color:#c40a0a;">
                                            <a href="{{ $loginUrl ?? rtrim(config('app.url'), '/') }}" style="color:#c40a0a;text-decoration:none;">{{ rtrim(config('app.url'), '/') }}</a>
                                        </td>
                                    </tr>
                                    <tr><td colspan="2" style="border-top:1px dashed #b7c1e3;"></td></tr>
                                    <tr>
                                        <td style="width:130px;padding:18px 0;font-size:22px;font-weight:700;color:#06145c;">Số dư:</td>
                                        <td style="padding:18px 0;font-size:24px;font-weight:900;color:#c40a0a;">{{ number_format((float) ($remaining ?? 0), 2) }} USD</td>
                                    </tr>
                                    <tr><td colspan="2" style="border-top:1px dashed #b7c1e3;"></td></tr>
                                    <tr>
                                        <td style="width:130px;padding:18px 0;font-size:22px;font-weight:700;color:#06145c;">Sử dụng:</td>
                                        <td style="padding:18px 0;font-size:24px;font-weight:900;color:#c40a0a;">{{ $used !== null ? number_format((float) $used, 2) : '-' }}</td>
                                    </tr>
                                    <tr><td colspan="2" style="border-top:1px dashed #b7c1e3;"></td></tr>
                                    <tr>
                                        <td style="width:130px;padding:18px 0;font-size:22px;font-weight:700;color:#06145c;">Thời gian:</td>
                                        <td style="padding:18px 0;font-size:21px;color:#061044;">{{ $sentAt ?? now()->format('Y-m-d H:i:s') }}</td>
                                    </tr>
                                </table>

                                <div style="text-align:center;margin-top:34px;">
                                    <a href="{{ $loginUrl ?? rtrim(config('app.url'), '/') }}" style="display:inline-block;background:#06145c;color:#ffffff;text-decoration:none;border-radius:10px;padding:14px 30px;font-size:17px;font-weight:900;">
                                        Mở XLAP ngay
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 50px 48px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #f7c66a;background:#fff8e8;border-radius:12px;">
                                <tr>
                                    <td style="width:92px;padding:26px 0 26px 42px;">
                                        <div style="width:54px;height:54px;border-radius:14px;background:#f5b000;color:#ffffff;font-size:36px;font-weight:900;line-height:54px;text-align:center;">!</div>
                                    </td>
                                    <td style="padding:26px 34px 26px 0;font-size:21px;line-height:1.45;font-weight:800;color:#061044;">
                                        Vui lòng nạp thêm tiền hoặc quota rồi bấm Continue hoặc Retry trên item đang dừng.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
