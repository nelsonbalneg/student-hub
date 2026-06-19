import re

file_path = r"c:\laragon\www\studenthub\resources\js\pages\StudentProfile\Index.vue"
with open(file_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

stack = []
for idx in range(2900, 4950):
    line_num = idx + 1
    line = lines[idx]
    
    # Find all opening and closing tags on this line
    tokens = re.findall(r'</?(?:div|template|section|aside|table|thead|tbody|tr|td|th)\b[^>]*>', line)
    for token in tokens:
        is_closing = token.startswith('</')
        tag_name = re.match(r'</?([a-zA-Z0-9-]+)', token).group(1)
        
        if is_closing:
            if not stack:
                print(f"Error: Closing tag {token} at line {line_num} has no matching opening tag")
            else:
                last_tag, last_line = stack.pop()
                if last_tag != tag_name:
                    print(f"Error: Mismatched tag on line {line_num}. Closing {token} but expected {last_tag} (opened on line {last_line})")
        else:
            # Self-closing check
            if token.endswith('/>'):
                continue
            stack.append((tag_name, line_num))

print("Stack at end of range:")
for tag, line in stack:
    print(f"  Unclosed {tag} opened on line {line}")
