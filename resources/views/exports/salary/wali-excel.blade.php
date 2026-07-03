<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; font-size: 11px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h3>Danh sách lương {{ $monthLabel }}</h3>
    <table>
        <thead>
            <tr>
                <th>Nhân viên</th>
                <th>Lương cơ bản</th>
                <th>Lương cứng biến động</th>
                <th>Điểm hiệu suất</th>
                <th>Đi trễ (phút)</th>
                <th>Điểm trừ</th>
                <th>Xin nghỉ</th>
                <th>Số ngày được nghỉ</th>
                <th>Nghỉ vượt</th>
                <th>Công chuẩn</th>
                <th>Công thực tế</th>
                <th>Điểm tính lương</th>
                <th>Thưởng ngày</th>
                <th>Bổ sung</th>
                <th>Tiền khác</th>
                <th>Note</th>
                <th>Tổng lương</th>
                <th>Tiền điểm lẻ</th>
                <th>Hoa hồng</th>
                <th>Thực nhận</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->employee_name }}</td>
                    <td class="right">{{ number_format((float) $row->base_salary, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->variable_salary, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->performance_score, 1, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->late_minutes, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->late_days, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->leave_days, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->allowed_leave_days, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format(max(0, (float) $row->leave_days - (float) $row->allowed_leave_days), 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->standard_work_days, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->actual_work_days, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->score, 1, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->daily_bonus, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->supplement, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->other_money, 0, ',', '.') }}</td>
                    <td>{{ $row->note }}</td>
                    <td class="right">{{ number_format((float) $row->total_salary, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->odd_point_money, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->commission, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->net_received, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
