#!/bin/bash

# Function to check if a port is in use
check_port() {
    local port=$1
    # Try to create a temporary socket to check port availability
    if timeout 1 bash -c "cat < /dev/tcp/localhost/$port" 2>/dev/null; then
        return 0  # Port is in use
    else
        return 1  # Port is available
    fi
}

# Find first available port starting from 8001
port=8001
max_port=8100  # Set a reasonable maximum port to try

while [ $port -le $max_port ]; do
    if ! check_port $port; then
        echo "Found available port: $port"
        break
    fi
    echo "Port $port is in use, trying next port..."
    port=$((port + 1))
done

if [ $port -gt $max_port ]; then
    echo "Error: Could not find an available port between 8001 and $max_port"
    exit 1
fi

echo "Starting PHP development server on port $port"
echo "Server will be available at: http://localhost:$port"

# Start PHP development server
php -S localhost:$port -t . 