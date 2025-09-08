# JavaScript Widgets for Go High Level Integration

This folder contains ready-to-use JavaScript widgets that display remaining seats for your special offers on Go High Level pages.

## 🚀 Quick Setup

### 1. API Configuration
Before using any widget, update the API URL in each HTML file:

```javascript
const API_URL = 'https://yourdomain.com/wp-json/llm/v1/seats-remaining';
const SEAT_LIMIT = 300; // Your campaign limit
```

Replace `yourdomain.com` with your actual WordPress site domain.

### 2. Test Your API
Visit this URL in your browser to test: `https://yourdomain.com/wp-json/llm/v1/seats-remaining?limit=300`

You should see JSON response like:
```json
{
  "total_limit": 300,
  "current_count": 253,
  "remaining": 47,
  "percentage_filled": 84.33,
  "status": "low",
  "last_updated": "2024-01-15T10:30:00+00:00"
}
```

## 📊 Available Widgets

### 1. Simple Counter (`simple-counter.html`)
**Best for:** Headers, hero sections, call-to-action areas

**Features:**
- Clean, modern design with gradient background
- Auto-updates every 60 seconds
- Color changes based on availability (green → orange → red)
- Pulse animation when urgent

**Usage:** Copy the HTML + CSS + Script sections into your Go High Level page

---

### 2. Progress Bar (`progress-bar.html`)
**Best for:** Detailed landing pages, sales pages

**Features:**
- Visual progress bar showing fill percentage
- Statistics display (taken/remaining/percentage)
- Professional card-style design
- Updates every 45 seconds

**Perfect for:** Creating social proof and visual urgency

---

### 3. Urgency Banner (`urgency-banner.html`)
**Best for:** Top of sales pages, checkout pages

**Features:**
- Large, attention-grabbing banner
- Animated shine effect and pulsing
- Different states: Available → Low → Urgent → Sold Out
- Big counter numbers for impact
- Updates every 30 seconds (more frequent)

**High conversion:** Maximum urgency and visibility

---

### 4. Compact Inline (`compact-inline.html`)
**Best for:** Inside text, button labels, small spaces

**Features:**
- Tiny, inline widget that fits in sentences
- Can be used multiple times on same page
- Perfect for button text or inline mentions
- Updates every 2 minutes

**Example Usage:**
```html
<p>Join now while <span id="seats-compact-inline">...</span>!</p>
<button>Get Started (<span id="seats-compact-inline">...</span>)</button>
```

## 🎨 Customization

### Colors & Styling
Each widget uses CSS classes you can customize:
- `.seats-counter-simple` - Simple counter
- `.seats-widget-progress` - Progress bar widget
- `.seats-urgency-banner` - Urgency banner
- `.seats-compact-inline` - Inline widget

### Status-Based Styling
Widgets automatically apply classes based on availability:
- `.available` - Good availability (green theme)
- `.low` - Low availability (orange theme)  
- `.urgent` - Very low availability (red theme, animations)

### Update Frequencies
Each widget has different update intervals:
- **Urgency Banner:** 30 seconds (highest urgency)
- **Progress Bar:** 45 seconds
- **Simple Counter:** 60 seconds  
- **Compact Inline:** 2 minutes (lowest server impact)

You can modify these by changing the `setInterval` values.

## 🔧 Advanced Configuration

### Custom Thresholds
Modify the status thresholds in your WordPress plugin:
```php
// In llm-prompts.php, get_seats_remaining() method:
if ($remaining <= 10) {
    $status = 'urgent';     // Red, animations
} elseif ($remaining <= 50) {
    $status = 'low';        // Orange
} else {
    $status = 'available';  // Green
}
```

### Error Handling
All widgets include fallback content if the API fails:
- Simple counter: "Limited seats available - Secure yours now!"
- Progress bar: Shows "?" for unknown values
- Urgency banner: Shows generic "Limited seats" message
- Compact inline: Shows "Limited seats!"

### CORS Headers
The API automatically includes CORS headers for Go High Level:
```php
$response->header('Access-Control-Allow-Origin', '*');
$response->header('Access-Control-Allow-Methods', 'GET');
```

## 📱 Mobile Responsiveness

All widgets include mobile-responsive CSS:
- Text sizes scale down appropriately
- Layouts adapt to smaller screens
- Touch-friendly elements

## 🔄 Real-Time Updates

### Auto-Refresh
All widgets automatically refresh their data:
- No page reload required
- Updates happen in background
- Smooth transitions between states

### Manual Refresh
You can also trigger updates manually via JavaScript:
```javascript
updateSeatCounter(); // For simple counter
updateProgressWidget(); // For progress bar
// etc.
```

## 📈 Conversion Optimization Tips

### 1. Widget Placement
- **Hero section:** Use Urgency Banner for maximum impact
- **Above fold:** Use Simple Counter or Progress Bar
- **Throughout content:** Use Compact Inline widgets
- **Near CTA buttons:** Use any widget to drive urgency

### 2. A/B Testing
Test different widgets to see what converts best:
- Urgency Banner vs Simple Counter
- With/without progress bars
- Different update frequencies

### 3. Scarcity Marketing
- Set SEAT_LIMIT lower than actual for artificial scarcity
- Use urgent status messages when below thresholds
- Combine with countdown timers for double urgency

## 🛠️ Troubleshooting

### Widget Shows "Loading..." Forever
1. Check API URL is correct
2. Ensure WordPress site is accessible
3. Check browser console for CORS errors
4. Verify API endpoint returns valid JSON

### Styling Issues
1. Ensure CSS is included before HTML
2. Check for CSS conflicts with Go High Level styles
3. Use browser inspector to debug layout issues

### Update Issues
1. Check network connectivity
2. Verify API endpoint is working
3. Look for JavaScript errors in console
4. Ensure setInterval is running

## 📞 Support

For issues with:
- **WordPress plugin:** Check WordPress admin dashboard
- **API endpoints:** Test directly in browser
- **Go High Level integration:** Check their documentation
- **Custom modifications:** Review JavaScript console for errors

---

Ready to boost your conversions with real-time seat counters! 🚀