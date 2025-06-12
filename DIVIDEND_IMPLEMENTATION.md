# Dividend Tracking Implementation

## Overview

This implementation adds comprehensive dividend tracking functionality to your stock portfolio management system using Yahoo Finance API. It tracks dividend eligibility, received dividends, and accounts for dividends in ROI calculations.

## Features Implemented

### 1. Database Structure

- **`dividend_payments`** table: Stores dividend announcements and payments from Yahoo Finance
- **`user_dividend_records`** table: Tracks user-specific dividend eligibility and receipt status

### 2. Yahoo Finance API Integration

- Fetches dividend data using multiple Yahoo Finance endpoints:
    - `quoteSummary` API for detailed dividend information
    - Chart API for historical dividend data
- Automatic detection of upcoming and historical dividends
- Rate limiting and error handling for API calls

### 3. Dividend Service (`DividendService`)

#### Key Methods:

- `fetchDividendDataFromYahoo()`: Retrieves dividend data from Yahoo Finance
- `updateDividendData()`: Updates dividend information for stocks
- `calculateDividendEligibility()`: Determines user eligibility based on holdings
- `getUserDividendSummary()`: Provides portfolio-wide dividend summary
- `getROIWithDividends()`: Calculates ROI including dividend returns

### 4. Console Commands

```bash
# Update dividend data for all stocks
php artisan dividends:update

# Update dividend data for specific stocks
php artisan dividends:update --stock=RELIANCE --stock=WIPRO
```

### 5. API Endpoints

- `POST /equity-holding/update-dividend-data`: Update dividend data for holdings
- `GET /equity-holding/{stockId}/dividend-details`: Get detailed dividend info for a stock
- `POST /equity-holding/mark-dividend-received`: Mark dividend as received

### 6. UI Enhancements

#### Portfolio Summary Cards

- **Dividends Received**: Shows total dividends received with pending amounts
- **Upcoming Dividends**: Count of stocks with upcoming dividend payments

#### Holdings Table & Cards

- **Dividend Column**: Shows dividend status for each holding:
    - ✅ Total dividends received (green)
    - 🟠 Pending dividends (orange)
    - 📊 Dividend yield percentage (blue)
    - 📅 Upcoming dividend indicator (purple)

#### Mobile View Enhancements

- Dedicated dividend information section in mobile cards
- Color-coded badges for different dividend statuses

### 7. ROI Calculations Enhanced

- **Basic ROI**: Capital appreciation only
- **Dividend-Adjusted ROI**: Includes dividend returns
- **Dividend Yield**: Percentage return from dividends alone

## How It Works

### 1. Dividend Data Collection

1. Yahoo Finance API provides dividend calendar events and historical data
2. System extracts ex-dividend dates, payment dates, and amounts
3. Data is stored in `dividend_payments` table

### 2. Eligibility Calculation

1. For each dividend announcement, system checks user holdings on ex-dividend date
2. Only shares held before ex-dividend date qualify for dividend
3. Creates records in `user_dividend_records` table

### 3. Status Tracking

- **Qualified**: User owned shares on ex-dividend date
- **Received**: Dividend payment date has passed
- **Credited**: User manually confirmed receipt (optional)

### 4. ROI Enhancement

Original ROI = (Current Value - Investment) / Investment × 100
Enhanced ROI = (Current Value - Investment + Dividends) / Investment × 100

## Usage Instructions

### 1. Initial Setup

```bash
# Run migrations
php artisan migrate

# Update dividend data for your holdings
php artisan dividends:update
```

### 2. Regular Updates

- Use the "Sync Dividends" button in the UI
- Or run the console command periodically
- Recommended: Daily or weekly updates

### 3. Manual Dividend Tracking

- Mark dividends as "received" when credited to your account
- Add notes for record keeping
- Track dividend yield performance

### 4. Viewing Dividend Information

- **Main Dashboard**: See portfolio-wide dividend summary
- **Holdings Table**: Individual stock dividend status
- **Mobile View**: Condensed dividend information in cards

## Data Sources

### Yahoo Finance APIs Used:

1. **quoteSummary API**:

    - Modules: `calendarEvents`, `summaryDetail`
    - Provides upcoming dividend dates and rates

2. **Chart API**:
    - Event type: `dividends`
    - Provides historical dividend payments

### Sample API Response:

```json
{
    "calendarEvents": {
        "exDividendDate": "2024-03-15T00:00:00.000Z",
        "dividendDate": "2024-03-22T00:00:00.000Z"
    },
    "summaryDetail": {
        "dividendRate": 2.5,
        "dividendYield": 0.025
    }
}
```

## Testing

### 1. Test with Sample Stocks

```bash
# Test with major dividend-paying stocks
php artisan dividends:update --stock=RELIANCE --stock=TCS --stock=INFY
```

### 2. Verify Data

- Check `dividend_payments` table for new entries
- Verify `user_dividend_records` for your holdings
- Test UI updates by refreshing portfolio view

### 3. API Testing

```bash
# Test dividend details endpoint
curl -X GET "http://your-app.com/equity-holding/{stockId}/dividend-details"

# Test dividend update
curl -X POST "http://your-app.com/equity-holding/update-dividend-data"
```

## Benefits

### 1. Complete Portfolio View

- True ROI including dividend returns
- Accurate performance tracking
- Dividend income monitoring

### 2. Tax Planning

- Track dividend income for tax purposes
- Identify high-yield holdings
- Plan for dividend tax implications

### 3. Investment Strategy

- Identify consistent dividend payers
- Monitor dividend yield trends
- Plan dividend-focused portfolios

### 4. Automation

- Automatic dividend detection
- Real-time eligibility calculation
- Integrated with existing portfolio system

## Troubleshooting

### Common Issues:

1. **No dividend data found**: Some stocks may not pay dividends
2. **API rate limits**: Yahoo Finance has informal rate limits
3. **Date mismatches**: Ensure transaction dates are accurate for eligibility

### Solutions:

1. Verify stock symbols and check if they're dividend-paying
2. Add delays between API calls (implemented: 0.5s)
3. Review transaction history for accurate ex-dividend date holdings

## Future Enhancements

- Email notifications for upcoming dividends
- Dividend reinvestment tracking (DRIP)
- Dividend calendar view
- Tax-loss harvesting integration
- Sector-wise dividend analysis
