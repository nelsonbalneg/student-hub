import re

file_path = r"c:\laragon\www\studenthub\resources\js\pages\StudentProfile\Index.vue"
with open(file_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

stack = []
for idx in range(4820, 4920):
    line_num = idx + 1
    line = lines[idx]
    
    tokens = re.findall(r'</?(?:[a-zA-Z0-9-]+)\b[^>]*>', line)
    for token in tokens:
        is_closing = token.startswith('</')
        tag_name = re.match(r'</?([a-zA-Z0-9-]+)', token).group(1).lower()
        
        if is_closing:
            if not stack:
                print(f"Error: Extra closing tag {token} at line {line_num}")
            else:
                last_tag, last_line = stack.pop()
                if last_tag != tag_name:
                    print(f"Error: Mismatched tag on line {line_num}. Closing {token} (tag: {tag_name}) but expected {last_tag} (opened on line {last_line})")
                else:
                    print(f"  Closed {tag_name} (opened line {last_line}) at line {line_num}")
        else:
            if token.endswith('/>') or tag_name in ['img', 'br', 'hr', 'input']:
                continue
            stack.append((tag_name, line_num))
            print(f"  Opened {tag_name} at line {line_num}")

print("\nUnresolved tags:")
for tag, line in stack:
    print(f"  Unclosed {tag} opened on line {line}")
