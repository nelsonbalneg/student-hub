import re

file_path = r"c:\laragon\www\studenthub\resources\js\pages\StudentProfile\Index.vue"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# Let's clean comments first to avoid matching tags in comments
content_clean = re.sub(r'<!--.*?-->', '', content, flags=re.DOTALL)

# Find all HTML-like tags, including their line numbers
tokens = []
line_starts = [0]
for m in re.finditer(r'\n', content_clean):
    line_starts.append(m.end())

def get_line_num(pos):
    # binary search or simple search
    for idx, start in enumerate(line_starts):
        if pos < start:
            return idx
    return len(line_starts)

# We want to match tags: <tag_name ...> or </tag_name>
# Let's find all matches
tag_pattern = re.compile(r'<(/?)([a-zA-Z0-9-]+)(?:\s+[^>]*)?>', re.DOTALL)
stack = []

for m in tag_pattern.finditer(content_clean):
    is_closing = bool(m.group(1))
    tag_name = m.group(2).lower()
    start_pos = m.start()
    line_num = get_line_num(start_pos)
    
    # We only care about layout and table tags to keep it simple and clean
    if tag_name not in ['div', 'template', 'section', 'aside', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'button', 'span']:
        continue
        
    full_tag = m.group(0)
    # Check if self-closing
    if full_tag.endswith('/>'):
        continue
        
    if is_closing:
        if not stack:
            print(f"Error: Extra closing tag {full_tag} at line {line_num}")
        else:
            last_tag, last_line, last_full = stack.pop()
            if last_tag != tag_name:
                print(f"Error: Mismatched tag on line {line_num}. Closing {full_tag} but expected {last_tag} (opened on line {last_line} as {last_full})")
    else:
        stack.append((tag_name, line_num, full_tag))

print("\nRemaining unclosed tags in stack:")
for tag, line, full in stack:
    if line >= 2000 and line <= 5500: # Focus on the main profile area
        print(f"  Unclosed {tag} opened on line {line} as {full[:60]}...")
