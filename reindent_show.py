from pathlib import Path
p = Path(r'd:\XAMPP\htdocs\Timesheet_Website\resources\views\approvals\ot-forms\show.blade.php')
lines = p.read_text().splitlines()
start = None
end = None
for i, line in enumerate(lines):
    if line.strip() == '<div class="space-y-6">':
        start = i + 1
    if start is not None and line.strip() == '</div>' and i > start:
        end = i
        break
if start and end:
    new_lines = lines[:start]
    for line in lines[start:end]:
        if line.startswith('                '):  # 16 spaces -> 12 spaces
            new_lines.append('            ' + line[16:])
        elif line.startswith('                    '):  # 20 spaces -> 16 spaces
            new_lines.append('                ' + line[20:])
        elif line.startswith('                        '):  # 24 spaces -> 20 spaces
            new_lines.append('                    ' + line[24:])
        elif line.startswith('                            '):  # 28 spaces -> 24 spaces
            new_lines.append('                        ' + line[28:])
        else:
            new_lines.append(line)
    new_lines.extend(lines[end:])
    p.write_text('\n'.join(new_lines) + '\n')
    print('Reindented lines', start, 'to', end)
else:
    print('Could not find boundaries', start, end)
