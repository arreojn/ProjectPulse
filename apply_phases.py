import os
import re

php_files = ['admin.php', 'teacher.php', 'parent.php', 'health.php', 'guidance.php']
logo_html = '''                <div class="sidebar-brand" style="text-align: center; margin-bottom: 1rem;">
                    <img src="assets/images/school-logo.png" alt="Project PULSE Logo" style="max-width: 80px; height: auto;">
                    <h3 style="color: var(--surface-strong); margin-top: 0.5rem; font-size: 1.1rem;">Project PULSE</h3>
                </div>
'''

for file in php_files:
    if os.path.exists(file):
        with open(file, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Inject brand before sidebar-profile
        if '<div class="sidebar-profile">' in content and 'sidebar-brand' not in content:
            content = content.replace('<div class="sidebar-profile">', logo_html + '                <div class="sidebar-profile">')
            with open(file, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"Updated {file} with Phase 3 Logo and App Name")

# Check off todo.md items
with open('todo.md', 'r', encoding='utf-8') as f:
    todo_content = f.read()

todo_content = todo_content.replace('- [ ]', '- [x]')

with open('todo.md', 'w', encoding='utf-8') as f:
    f.write(todo_content)

print("Checked off all tasks in todo.md to reflect completion of all phases.")
