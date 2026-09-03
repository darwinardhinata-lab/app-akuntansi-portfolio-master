import os, subprocess, sys

errors = []
for root, dirs, files in os.walk('app'):
    for f in files:
        if f.endswith('.php'):
            path = os.path.join(root, f)
            result = subprocess.run(['php', '-l', path], capture_output=True, text=True)
            if 'No syntax errors' not in result.stdout:
                errors.append(path + ': ' + (result.stdout + result.stderr).strip())

if errors:
    for e in errors:
        print(e)
    print(f"\nTotal files with errors: {len(errors)}")
    sys.exit(1)
else:
    print("All PHP files: No syntax errors found!")
    sys.exit(0)