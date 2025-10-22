# Vira Code - Conditional Logic Documentation

## Overview

Conditional Logic allows you to control when and where your code snippets execute based on specific conditions. This powerful feature ensures your snippets only run when the right criteria are met.

## Getting Started

### Enabling Conditional Logic

1. Go to **Vira Code > Add New** or edit an existing snippet
2. Scroll down to the **Conditional Logic** section (located under the code editor)
3. Check the **"Enable"** checkbox
4. The conditional logic interface will appear

### Basic Workflow

1. **Enable Conditional Logic** - Check the enable checkbox
2. **Add Condition Group** - Click "Add Condition Group" button
3. **Set Conditions** - Choose condition type, operator, and value
4. **Test Rules** - Click "Check Rules" to verify your logic
5. **Save Snippet** - Rules are automatically saved with your snippet

## Condition Types

### 1. Page Type

Controls snippet execution based on WordPress page types.

**Available Values:**
```
home              - Homepage/blog index
front_page        - Static front page
single            - Single post pages
page              - Static pages
archive           - Archive pages
category          - Category archive pages
tag               - Tag archive pages
author            - Author archive pages
date              - Date archive pages
search            - Search results pages
404               - 404 error pages
attachment        - Attachment pages
singular          - Single post/page/custom post
admin             - WordPress admin area
feed              - RSS/Atom feeds
trackback         - Trackback pages
preview           - Preview pages
customize_preview - Customizer preview
```

**Custom Post Types:**
```
post              - Blog posts
product           - WooCommerce products
event             - Custom event posts
portfolio         - Portfolio items
testimonial       - Testimonials
```

**Example Usage:**
- Show snippet only on homepage: `page_type` = `equals` = `home`
- Hide from admin area: `page_type` = `not_equals` = `admin`
- Single posts only: `page_type` = `equals` = `single`

### 2. User Role

Controls snippet execution based on user roles and login status.

**Available Values:**
```
administrator     - Site administrators
editor           - Content editors
author           - Content authors
contributor      - Content contributors
subscriber       - Basic subscribers
logged_in        - Any logged-in user
logged_out       - Visitors (not logged in)
guest            - Same as logged_out
```

**Custom Roles (if available):**
```
shop_manager     - WooCommerce shop manager
customer         - WooCommerce customer
moderator        - Forum moderator
```

**Example Usage:**
- Admin only snippet: `user_role` = `equals` = `administrator`
- Logged-in users only: `user_role` = `equals` = `logged_in`
- Hide from guests: `user_role` = `not_equals` = `logged_out`

### 3. Device Type

Controls snippet execution based on device type detection.

**Available Values:**
```
desktop          - Desktop computers
mobile           - Mobile phones
tablet           - Tablet devices
```

**Example Usage:**
- Mobile users only: `device_type` = `equals` = `mobile`
- Hide from mobile: `device_type` = `not_equals` = `mobile`
- Desktop and tablet: `device_type` = `not_equals` = `mobile`

### 4. URL Pattern

Controls snippet execution based on URL matching patterns.

**Exact URLs:**
```
/                - Homepage
/about/          - About page
/contact/        - Contact page
/shop/           - Shop page
/blog/           - Blog page
```

**URL Patterns:**
```
/product/        - Any product page
/category/       - Any category page
/tag/            - Any tag page
/author/         - Any author page
/page/           - Any static page
```

**With Parameters:**
```
/product/123     - Specific product
/category/tech   - Specific category
/search?q=       - Search pages
/?p=123          - Post with ID
```

**Regex Patterns:**
```
^/shop/.*        - Starts with /shop/
.*\.pdf$         - Ends with .pdf
/product/[0-9]+  - Product with numeric ID
/(en|fr|de)/.*   - Multi-language sites
```

**Example Usage:**
- Shop pages only: `url_pattern` = `contains` = `/shop/`
- Exclude admin URLs: `url_pattern` = `not_contains` = `/wp-admin/`
- Specific page: `url_pattern` = `equals` = `/contact/`

## Operators

### Basic Operators
- **equals** - Exact match
- **not_equals** - Does not match
- **contains** - Contains the value (for URL patterns)

### Advanced Operators (URL Pattern)
- **starts_with** - URL starts with value
- **ends_with** - URL ends with value
- **regex** - Regular expression matching

## Logic Operators

### Group Logic
- **AND** - All conditions in the group must be true
- **OR** - Any condition in the group can be true

### Multiple Groups
- Groups are combined with **AND** logic
- Group 1 AND Group 2 AND Group 3...

## Common Use Cases

### 1. Homepage Banner
```
Condition: page_type = equals = home
Use Case: Show a special banner only on the homepage
```

### 2. Admin-Only Debug Info
```
Condition: user_role = equals = administrator
Use Case: Display debug information only for administrators
```

### 3. Mobile-Specific Styles
```
Condition: device_type = equals = mobile
Use Case: Apply mobile-specific CSS styles
```

### 4. Shop Section Features
```
Condition: url_pattern = contains = /shop/
Use Case: Add shopping cart functionality to shop pages
```

### 5. Logged-In User Welcome
```
Condition: user_role = equals = logged_in
Use Case: Show personalized welcome message for logged-in users
```

### 6. Exclude Admin Area
```
Condition: page_type = not_equals = admin
Use Case: Prevent frontend scripts from loading in admin
```

### 7. Blog Post Enhancements
```
Condition: page_type = equals = single
Use Case: Add social sharing buttons to blog posts
```

### 8. Category-Specific Features
```
Condition: url_pattern = contains = /category/news/
Use Case: Add news-specific functionality to news category
```

## Advanced Examples

### Complex Multi-Condition Rules

**Example 1: Admin Users on Frontend Only**
```
Group 1 (AND):
- user_role = equals = administrator
- page_type = not_equals = admin
```

**Example 2: Mobile Users on Shop Pages**
```
Group 1 (AND):
- device_type = equals = mobile
- url_pattern = contains = /shop/
```

**Example 3: Logged-in Users OR Administrators**
```
Group 1 (OR):
- user_role = equals = logged_in
- user_role = equals = administrator
```

**Example 4: Multiple Page Types**
```
Group 1 (OR):
- page_type = equals = home
- page_type = equals = front_page
- page_type = equals = single
```

## Testing Your Rules

### Using "Check Rules" Button

1. Set up your conditional logic rules
2. Click the **"Check Rules"** button
3. Review the test result:
   - **PASS** ✅ = Snippet will execute with current conditions
   - **FAIL** ❌ = Snippet will be blocked with current conditions

### Understanding Test Results

The test evaluates your rules against the current page context:
- Current page type
- Current user role
- Current device type
- Current URL

**Example Test Scenarios:**
- Testing homepage rule while on homepage = PASS
- Testing admin rule while logged in as admin = PASS
- Testing mobile rule while on desktop = FAIL

## Best Practices

### 1. Keep Rules Simple
- Start with simple conditions
- Add complexity gradually
- Test each rule thoroughly

### 2. Use Descriptive Logic
- Choose clear condition types
- Use appropriate operators
- Test with realistic scenarios

### 3. Performance Considerations
- Avoid overly complex regex patterns
- Limit the number of conditions per group
- Test performance impact on your site

### 4. Testing Strategy
- Test rules in different contexts
- Verify both PASS and FAIL scenarios
- Test with different user roles and devices

### 5. Documentation
- Document complex rule logic
- Note the purpose of each condition
- Keep track of rule changes

## Troubleshooting

### Common Issues

**Rules Not Working:**
1. Check if Conditional Logic is enabled
2. Verify condition values are correct
3. Test rules using "Check Rules" button
4. Check for typos in values

**Unexpected Behavior:**
1. Review logic operators (AND vs OR)
2. Check condition precedence
3. Test individual conditions
4. Verify context (page type, user role, etc.)

**Performance Issues:**
1. Simplify complex regex patterns
2. Reduce number of conditions
3. Use more specific condition types
4. Monitor site performance

### Debug Information

When testing rules, pay attention to:
- Current page context
- User login status
- Device type detection
- URL pattern matching

## Import/Export

### Exporting Rules
1. Click **"Export Rules"** button
2. JSON file downloads automatically
3. Contains all rule configuration
4. Includes snippet metadata

### Importing Rules
1. Click **"Import Rules"** button
2. Follow import instructions
3. Contact administrator for bulk imports
4. Verify imported rules work correctly

## API Integration

### WordPress Hooks

The conditional logic system provides several hooks for developers:

```php
// Before evaluation
do_action('vira_code/before_conditional_evaluation', $snippet);

// After evaluation
do_action('vira_code/after_conditional_evaluation', $snippet, $result, $time);

// On evaluation error
do_action('vira_code/conditional_evaluation_error', $snippet, $error);

// When rules are saved
do_action('vira_code/conditional_logic_rules_saved', $snippet_id, $rules);
```

### Custom Condition Types

Developers can register custom condition types:

```php
// Register custom evaluator
add_filter('vira_code/conditional_logic_evaluators', function($evaluators) {
    $evaluators['custom_type'] = new CustomEvaluator();
    return $evaluators;
});
```

## Security Considerations

### Safe Practices
- Validate all condition values
- Sanitize user inputs
- Use appropriate WordPress capabilities
- Test security implications

### Restricted Features
- Custom PHP conditions have security restrictions
- Dangerous functions are blocked
- User input is sanitized
- Admin capabilities required for sensitive operations

## Support

For additional help with Conditional Logic:

1. **Test thoroughly** using the "Check Rules" button
2. **Start simple** with basic conditions
3. **Review documentation** for value templates
4. **Contact support** for complex scenarios

---

*This documentation covers the complete Conditional Logic system in Vira Code. For the most up-to-date information, always refer to the latest version of this documentation.*