# Equity Holding Feature Setup Guide

## Overview

The Equity Holding feature allows users to track their stock investments, view portfolio performance, and manage stock transactions. It includes integration with Alpha Vantage API for real-time stock prices.

## Features Implemented

### ✅ Core Features

- **Navigation**: Added "Equity Holding" to sidebar navigation
- **Add Transactions**: Modal form to add stock purchase transactions
- **Portfolio View**: Display all stocks held by user
- **Grouping**: Multiple transactions of same stock are grouped together
- **Expandable Details**: Click + sign to view individual transactions
- **Responsive Design**: Works on desktop and mobile devices

### ✅ Database Structure

- `stocks` table: Store stock information (symbol, name, exchange, prices)
- `stock_transactions` table: Store individual buy/sell transactions
- Proper relationships and constraints

### ✅ API Integration

- Alpha Vantage API integration for stock prices
- Artisan command to update stock prices
- Service class for API calls with caching
- Support for NSE and BSE exchanges

## Setup Instructions

### 1. Database Migration

The migrations have been run automatically. Tables created:

- `stocks`
- `stock_transactions`

### 2. Alpha Vantage API Setup

1. Visit [Alpha Vantage](https://www.alphavantage.co/support/#api-key)
2. Sign up for a free API key (25 calls per day)
3. Add to your `.env` file:

```env
ALPHA_VANTAGE_API_KEY=your_actual_api_key_here
```

### 3. Seed Sample Data (Optional)

```bash
php artisan db:seed --class=StockSeeder
```

### 4. Update Stock Prices

```bash
php artisan stocks:update-prices
```

## Usage

### Adding Stock Transactions

1. Navigate to "Equity Holding" from the sidebar
2. Click "Add Transaction" button
3. Fill in the modal form:
    - **Stock Name**: e.g., "Infosys", "TCS", "RELIANCE"
    - **Exchange**: NSE or BSE
    - **Date**: Transaction date
    - **Quantity**: Number of shares purchased
    - **Price/Stock**: Price per share
    - **Broker**: (Optional) Broker name
    - **Total Charges**: Brokerage + taxes + other charges
    - **Net Amount**: Total amount paid
    - **Notes**: (Optional) Additional notes

### Viewing Portfolio

- **Summary Cards**: Total investment, current value, P&L, P&L %
- **Holdings Table**: Detailed view with all metrics
- **Expandable Rows**: Click the arrow to see individual transactions
- **Mobile Cards**: Responsive card view for mobile devices

### Key Metrics Displayed

- **Quantity**: Total shares held
- **Average Price**: Weighted average purchase price
- **Current Price**: Real-time price (when API is configured)
- **Investment**: Total amount invested
- **Current Value**: Current market value
- **P&L**: Unrealized profit/loss
- **P&L %**: Percentage gain/loss
- **Day Change**: Daily price movement

## API Endpoints

### Web Routes

- `GET /equity-holding` - View portfolio
- `POST /equity-holding` - Add new transaction
- `GET /equity-holding/{stockId}/transactions` - Get stock transactions

### Artisan Commands

- `php artisan stocks:update-prices` - Update stock prices from API

## File Structure

### Backend

```
app/
├── Models/
│   ├── Stock.php
│   └── StockTransaction.php
├── Http/Controllers/
│   └── EquityHoldingController.php
├── Services/
│   └── StockPriceService.php
└── Console/Commands/
    └── UpdateStockPrices.php

database/
├── migrations/
│   ├── create_stocks_table.php
│   └── create_stock_transactions_table.php
└── seeders/
    └── StockSeeder.php
```

### Frontend

```
resources/js/
├── pages/EquityHolding/
│   └── Index.tsx
└── components/
    └── app-sidebar.tsx (updated)
```

## Alternative Free APIs

If Alpha Vantage doesn't work well, consider these alternatives:

### 1. Yahoo Finance (via yfinance Python library)

- **Pros**: Completely free, extensive data
- **Cons**: Unofficial, might break
- **Usage**: Add `.NS` for NSE, `.BO` for BSE stocks

### 2. NSE Official API

- **Pros**: Official source, accurate
- **Cons**: Complex setup, rate limits
- **Coverage**: NSE stocks only

### 3. Polygon.io

- **Pros**: Professional grade
- **Cons**: Limited Indian coverage
- **Free Tier**: 5 calls per minute

### 4. Breeze API (ICICI Direct)

- **Pros**: Free for ICICI customers, Indian focus
- **Cons**: Requires ICICI Direct account
- **Features**: Real-time data, historical data

## Next Steps

### Suggested Enhancements

1. **Sell Transactions**: Add support for selling stocks
2. **Dividend Tracking**: Track dividend payments
3. **Reports**: Generate portfolio reports
4. **Alerts**: Price alerts and notifications
5. **Charts**: Price charts and technical indicators
6. **Export**: Export data to Excel/PDF
7. **Bulk Import**: Import transactions from CSV
8. **SIP Tracking**: Track systematic investment plans

### Production Considerations

1. **API Limits**: Implement proper rate limiting
2. **Caching**: Enhanced caching strategy
3. **Error Handling**: Better error handling and retry logic
4. **Validation**: More robust validation
5. **Security**: API key security and rotation
6. **Performance**: Database indexing and optimization

## Troubleshooting

### Common Issues

1. **API Key Not Working**: Verify the key is correct and active
2. **No Stock Data**: Check if symbols are correct (use .NS or .BO suffix)
3. **Rate Limit Exceeded**: Wait for rate limit reset or upgrade API plan
4. **Missing Transactions**: Ensure proper user authentication

### Error Messages

- "Alpha Vantage API key not found" - Add API key to .env file
- "No data found for symbol" - Try different symbol format or check spelling
- "Rate limit exceeded" - Wait and try again later

## Support

For issues or questions, check:

1. Laravel logs: `storage/logs/laravel.log`
2. Network tab in browser for API errors
3. Alpha Vantage documentation
4. Laravel documentation
