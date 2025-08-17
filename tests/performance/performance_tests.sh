#!/bin/bash

# Allow URL to be set via environment variable or first command-line argument, default to localhost for safety
URL="${URL:-${1:-http://localhost:8080/test-static}}"
REQUESTS=1000
CONCURRENCY=10
ITERATIONS=10

declare -a times=()
total=0

echo "Benchmarking: $URL"
echo "Requests per test: $REQUESTS"
echo "Concurrency: $CONCURRENCY"
echo "Iterations: $ITERATIONS"
echo "========================================"

# First, get a baseline memory reading
echo "Getting memory baseline..."
memory_response=$(curl -s "${URL}?memory=1")
baseline_memory=$(echo "$memory_response" | grep "Memory:" | awk '{print $2}')
echo "Baseline memory usage: ${baseline_memory} KB"
echo "----------------------------------------"

for i in $(seq 1 $ITERATIONS); do
    printf "Run %2d/%d: " $i $ITERATIONS
    
    # Run ab and extract time per request
    result=$(ab -n $REQUESTS -c $CONCURRENCY $URL 2>/dev/null)
    time_per_request=$(echo "$result" | grep "Time per request:" | head -1 | awk '{print $4}')
    requests_per_sec=$(echo "$result" | grep "Requests per second:" | awk '{print $4}')
    
    times+=($time_per_request)
    total=$(echo "$total + $time_per_request" | bc -l)
    
    printf "%.3f ms (%.2f req/s)\n" $time_per_request $requests_per_sec
done

# Calculate statistics
average=$(echo "scale=3; $total / $ITERATIONS" | bc -l)

# Find min and max
min=${times[0]}
max=${times[0]}
for time in "${times[@]}"; do
    if (( $(echo "$time < $min" | bc -l) )); then
        min=$time
    fi
    if (( $(echo "$time > $max" | bc -l) )); then
        max=$time
    fi
done

echo "========================================"
echo "Results:"
echo "Average Time per Request: $average ms"
echo "Min Time per Request: $min ms"
echo "Max Time per Request: $max ms"
echo "Range: $(echo "scale=3; $max - $min" | bc -l) ms"
echo "Baseline Memory Usage: ${baseline_memory} KB"

# Get final memory reading after stress test
echo "----------------------------------------"
echo "Getting post-test memory reading..."
final_memory_response=$(curl -s "${URL}?memory=1")
final_memory=$(echo "$final_memory_response" | grep "Memory:" | awk '{print $2}')
echo "Final memory usage: ${final_memory} KB"