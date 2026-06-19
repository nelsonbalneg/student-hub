import re

file_path = r"c:\laragon\www\studenthub\resources\js\pages\StudentProfile\Index.vue"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

content_clean = re.sub(r'<!--.*?-->', '', content, flags=re.DOTALL)

line_starts = [0]
for m in re.finditer(r'\n', content_clean):
    line_starts.append(m.end())

def get_line_num(pos):
    for idx, start in enumerate(line_starts):
        if pos < start:
            return idx
    return len(line_starts)

tag_pattern = re.compile(r'<(/?)([a-zA-Z0-9-]+)(?:\s+[^>]*)?>', re.DOTALL)
stack = []

for m in tag_pattern.finditer(content_clean):
    is_closing = bool(m.group(1))
    tag_name = m.group(2).lower()
    start_pos = m.start()
    line_num = get_line_num(start_pos)
    
    if tag_name not in ['div', 'template', 'section', 'aside', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'button', 'span']:
        continue
        
    full_tag = m.group(0)
    if full_tag.endswith('/>'):
        continue
        
    # Check if we just reached line 4821
    if line_num == 4821 and not is_closing:
        print(f"AT LINE 4821, STACK IS:")
        for tag, line, full in stack:
            print(f"  {tag} opened at line {line} as {full[:80]}")
        
    if is_closing:
        if stack:
            stack.pop()
    else:
        stack.append((tag_name, line_num, full_tag))
