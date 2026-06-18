@php
    $displayUrl = rtrim($appUrl, '/');
    $logoUrl = asset('images/offorest-logo.jpg');
    $loginName = $user->username ?: $user->email;
@endphp

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offorest gửi thông tin đăng nhập</title>
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
                                        <img src="{{ asset('images/offorest-logo.jpg') }}" alt="{{ config('app.name', 'Offorest') }}" width="96" style="display:block;width:96px;height:auto;object-fit:contain;">
                                        <div style="margin-top:8px;font-size:19px;font-weight:800;color:#07115d;">Offorest</div>
                                    </td>
                                    <td align="center">
                                        <div style="display:inline-block;background:#d50b0b;color:#ffffff;border-radius:10px;padding:13px 28px;font-size:25px;font-weight:900;letter-spacing:.5px;box-shadow:0 6px 14px rgba(213,11,11,.18);">
                                             📢 Thông Báo
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
                                    THÔNG TIN ĐĂNG NHẬP
                                </div>

                                <div style="text-align:center;margin-bottom:38px;">
                                    <div style="font-size:27px;line-height:1.35;color:#061044;">
                                        Chào mừng <span style="color:#c40a0a;font-weight:900;">{{ $user->name }}</span>,
                                    </div>
                                    <div style="margin-top:10px;font-size:22px;line-height:1.45;color:#061044;">
                                        Thông tin tài khoản đăng nhập của bạn là:
                                    </div>
                                </div>

                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        
                                        <td style="width:130px;padding:18px 0;font-size:25px;">🌐: </td>
                                        <td style="padding:18px 0;font-size:25px;font-weight:900;color:#c40a0a;">
                                            <a href="{{ $loginUrl }}" style="color:#c40a0a;text-decoration:none;">{{ $displayUrl }}</a>
                                        </td>
                                    </tr>
                                    <tr><td colspan="3" style="border-top:1px dashed #b7c1e3;"></td></tr>
                                    <tr>
                                       
                                        <td style="width:130px;padding:18px 0;font-size:25px;">👤: </td>
                                        <td style="padding:18px 0;font-size:25px;font-weight:900;color:#c40a0a;">{{ $loginName }}</td>
                                    </tr>
                                    <tr><td colspan="3" style="border-top:1px dashed #b7c1e3;"></td></tr>
                                    <tr>
                                      
                                       <td style="width:130px;padding:18px 0;font-size:25px;">🔑: </td>
                                        <td style="padding:18px 0;">
                                            <div style="font-size:25px;font-weight:900;color:#c40a0a;">{{ $plainPassword }}</div>
                                            <div style="margin-top:6px;font-size:16px;font-style:italic;color:#061044;">(vui long doi pass sau lan dau dang nhap).</div>
                                        </td>
                                    </tr>
                                </table>

                                <div style="text-align:center;margin-top:34px;">
                                    <a href="{{ $loginUrl }}" style="display:inline-block;background:#06145c;color:#ffffff;text-decoration:none;border-radius:10px;padding:14px 30px;font-size:17px;font-weight:900;">
                                        Đăng nhập ngay!
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
                                        Vui lòng bảo mật thông tin tài khoản của bạn để đảm bảo an toàn dữ liệu.
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
