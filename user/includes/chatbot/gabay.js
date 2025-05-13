// OrderKo Gabay Chatbot - Enhanced Customer Service with Clickable Suggestions (gabay version 1.0.0.2 koko)

;(() => {
  // DOM Elements
  const chatbotToggle = document.getElementById("chatbot-toggle")
  const chatbotContainer = document.getElementById("chatbot-container")
  const chatbotClose = document.getElementById("chatbot-close")
  const chatbotClear = document.getElementById("chatbot-clear")
  const chatbotSend = document.getElementById("chatbot-send")
  const chatbotInput = document.getElementById("chatbot-input")
  const chatbotMessages = document.getElementById("chatbot-messages")
  const typingIndicator = document.getElementById("typing-indicator")
  const quickTopics = document.getElementById("quick-topics")
  const quickTopicButtons = document.querySelectorAll(".quick-topic-button")
  const suggestionChips = document.getElementById("suggestion-chips")
  const helpPopup = document.getElementById("help-popup")
  const helpCloseButton = document.getElementById("help-close")
  const dontShowAgainCheckbox = document.getElementById("dont-show-again")
  const minimizeButton = document.getElementById("chatbot-minimize")
const feedbackButton = document.getElementById("chatbot-feedback")


  // State
  let isOpen = false
  let isMinimized = false
  let messageHistory = []
  let showingQuickTopics = true
  let userName = localStorage.getItem("orderko_userName") || null
  let currentConversationContext = null
  let awaitingResponse = false
  let lastMessageCategory = null
  let firstTimeOpen = localStorage.getItem("orderko_firstTimeOpen") !== "false"

  // Knowledge Base
  const knowledgeBase = {
    orderProcess: {
      main: "Para mag-order sa OrderKo:\n1. Browse businesses by category o search\n2. Select a business at piliin ang products\n3. Add to cart at i-review ang order\n4. Choose delivery o pickup\n5. Complete payment\n6. Track your order sa Orders tab",
      suggestions: ["Paano mag-browse ng businesses?", "Paano mag-checkout?", "Pwede bang mag-order in advance?"],
    },
    trackOrder: {
      main: "Para ma-track ang order mo:\n1. Tap sa 'Orders' tab sa bottom navigation\n2. Makikita mo ang lahat ng current at past orders mo\n3. Tap sa specific order para makita ang details at status\n4. Real-time updates ang makikita mo (Pending, Confirmed, Preparing, Ready, Completed)",
      suggestions: ["Ano ibig sabihin ng mga status?", "May problema ang order ko", "Paano mag-cancel ng order?"],
    },
    howToUse: {
      main: "Para gamitin ang OrderKo app:\n1. Create an account o mag-login\n2. Set your delivery location (pwede mong i-update anytime)\n3. Browse businesses by category (Food, Clothing, Crafts, Bakery, etc.)\n4. Explore products, add to cart, at checkout\n5. Track orders sa Orders tab\n6. Manage your profile sa Profile tab",
      suggestions: ["Paano i-setup ang profile ko?", "Paano mag-search ng products?", "Paano mag-change ng location?"],
    },
    businessInfo: {
      main: "Sa OrderKo, makikita mo ang iba't ibang local businesses:\n• Categories: Food, Clothing, Crafts, Bakery, Beauty, at marami pa\n• Business details: operating hours, location, contact info\n• Product listings with descriptions, prices, at availability\n• Reviews from other customers\n\nFeatured businesses ngayon: Maria's Bakeshop at Lola's Kitchen",
      suggestions: ["Ano ang Maria's Bakeshop?", "Ano ang Lola's Kitchen?", "Paano mag-search ng specific business?"],
    },
    contactSupport: {
      main: "Para sa customer support:\n• Email: support@orderko.com\n• Hotline: (02) 8123-4567\n• In-app chat: Tap sa Profile > Help & Support\n• Social media: @OrderKoPH sa Facebook at Instagram\n\nAvailable ang support team 7AM-10PM, 7 days a week.",
      suggestions: ["May problema ako sa order ko", "Paano mag-report ng issue?", "Saan makikita ang Help Center?"],
    },
    payment: {
      main: "Supported payment methods sa OrderKo:\n• Cash on Delivery/Pickup\n• Credit/Debit Cards\n• GCash\n• Maya\n• Bank Transfer\n\nLahat ng transactions ay secure at may confirmation receipt.",
      suggestions: [
        "Paano mag-pay gamit ang GCash?",
        "Paano mag-add ng credit card?",
        "May discount ba kung cash payment?",
      ],
    },
    delivery: {
      main: "Delivery options sa OrderKo:\n• Standard Delivery (within 1-3 hours, depende sa location)\n• Scheduled Delivery (pwede kang pumili ng specific time)\n• Express Delivery (30-45 minutes, may additional fee)\n• Pickup (no delivery fee)\n\nMay delivery tracking feature din para real-time updates.",
      suggestions: [
        "Magkano ang delivery fee?",
        "Paano mag-schedule ng delivery?",
        "Pwede bang i-change ang delivery address?",
      ],
    },
    account: {
      main: "Para sa account management:\n• Profile tab > Edit Profile para i-update ang personal info\n• Change password: Profile > Settings > Security\n• Address management: Profile > My Addresses\n• Payment methods: Profile > Payment Methods\n• Notifications settings: Profile > Settings > Notifications",
      suggestions: ["Paano mag-change ng password?", "Paano mag-add ng address?", "Paano i-delete ang account ko?"],
    },
    categories: {
      main: "Ang OrderKo ay may iba't ibang categories ng businesses:\n• Food: restaurants, cafes, food stalls\n• Clothing: local boutiques, thrift shops\n• Crafts: handmade products, souvenirs\n• Bakery: bread, pastries, cakes\n• Beauty: skincare, makeup, personal care\n• At marami pang iba!",
      suggestions: ["Ano ang top Food businesses?", "May Clothing stores ba?", "Paano mag-filter by category?"],
    },
    preOrder: {
      main: "Ang pre-order feature ng OrderKo:\n• Pwede kang umorder in advance (hanggang 7 days)\n• Perfect para sa special occasions o busy days\n• May discount options para sa early pre-orders\n• Guaranteed availability ng products\n• Pwedeng i-customize ang order details",
      suggestions: ["Paano mag-pre-order?", "May discount ba sa pre-order?", "Gaano katagal pwedeng mag-pre-order?"],
    },
    featuredBusinesses: {
      main: "Featured businesses ngayon sa OrderKo:\n\n• Maria's Bakeshop - Sikat sa kanilang pastries at specialty cakes. Best seller ang Ube Cheese Pandesal!\n\n• Lola's Kitchen - Authentic Filipino home-cooked meals. Try mo ang kanilang Kare-Kare at Sinigang!",
      suggestions: [
        "Paano pumunta sa Maria's Bakeshop?",
        "Ano ang best sellers sa Lola's Kitchen?",
        "May iba pa bang featured businesses?",
      ],
    },
    location: {
      main: "Ang OrderKo ay available sa Metro Manila at select areas sa Luzon. Para i-update ang location mo:\n1. Tap sa location icon sa taas ng screen\n2. Enter your new address o i-allow ang app na i-detect ang current location mo\n3. Confirm ang bagong location\n\nMakikita mo lang ang businesses na nag-seserve sa area mo.",
      suggestions: [
        "Available ba sa Quezon City?",
        "Paano mag-change ng location?",
        "Bakit limited ang businesses na nakikita ko?",
      ],
    },
    promos: {
      main: "Current promos sa OrderKo:\n• New User: ₱100 off sa first order (code: NEWUSER100)\n• Free Delivery: No minimum order (code: FREEDELIVERY)\n• Weekend Special: 15% off sa lahat ng orders (code: WEEKEND15)\n• Refer-a-Friend: ₱50 credits for both referrer and referee\n\nMay expiration dates ang mga codes, kaya gamitin agad!",
      suggestions: [
        "Paano gamitin ang promo code?",
        "Hanggang kailan valid ang WEEKEND15?",
        "Paano mag-refer ng friend?",
      ],
    },
  }

  // Specific responses for follow-up answers
  const specificResponses = {
    orderProcess: {
      browse:
        "Para mag-browse ng businesses:\n1. Scroll sa homepage para makita ang Featured Businesses\n2. Tap sa category icons (Food, Clothing, etc.) para makita ang businesses by category\n3. Use the search bar para hanapin ang specific business o product\n4. Tap sa 'View All' para makita lahat ng businesses\n\nMay filter options din para sa ratings, distance, at availability.",
      checkout:
        "Pagkatapos mag-add to cart, tap 'Checkout' para mag-proceed sa payment:\n1. Review your order details\n2. Choose delivery o pickup\n3. Select payment method (COD, GCash, Credit Card, etc.)\n4. Enter promo code kung meron\n5. Tap 'Place Order'\n\nMakakakuha ka ng confirmation at order tracking number.",
      advance:
        "Oo, pwede kang mag-pre-order sa OrderKo:\n1. Select the business at products\n2. Sa checkout page, choose 'Schedule for Later'\n3. Select ang date at time na gusto mo\n4. Complete the checkout process\n\nPerfect ito para sa special occasions o busy days. May mga businesses din na nag-o-offer ng discount para sa advance orders!",
    },
    trackOrder: {
      status:
        "Ang ibig sabihin ng bawat order status:\n• Pending: Waiting for business confirmation\n• Confirmed: Order accepted by business\n• Preparing: Business is preparing your order\n• Ready: Order is ready for pickup/delivery\n• Completed: Order has been delivered/picked up\n• Cancelled: Order has been cancelled\n\nMay real-time updates ka sa app at optional SMS notifications.",
      problem:
        "Kung may problema sa order mo:\n1. Go to Orders tab > select the problematic order\n2. Tap 'Report Issue' button\n3. Select ang type ng issue (wrong item, incomplete order, etc.)\n4. Provide details at photos kung needed\n5. Submit ang report\n\nMag-re-respond ang OrderKo support team within 24 hours. For urgent concerns, you can also call our hotline: (02) 8123-4567.",
      cancel:
        "Para mag-cancel ng order:\n1. Go to Orders tab > select the order you want to cancel\n2. Tap 'Cancel Order' button (available lang kung Pending o Confirmed pa ang status)\n3. Select ang reason for cancellation\n4. Confirm the cancellation\n\nKung Preparing na ang status, hindi na pwedeng i-cancel online. Contact mo na lang directly ang business o OrderKo support.",
    },
    howToUse: {
      profile:
        "Para i-setup ang profile mo:\n1. Tap sa 'Profile' tab sa bottom navigation\n2. Tap 'Edit Profile'\n3. Add/update your name, contact info, at profile picture\n4. Add delivery addresses sa 'My Addresses'\n5. Add payment methods sa 'Payment Methods'\n6. Set notification preferences sa 'Settings'\n\nComplete profile helps for faster checkout!",
      search:
        "Para mag-search ng products:\n1. Tap sa search bar sa taas ng screen\n2. Type the product name, category, o business name\n3. Press search o Enter\n4. Use filters (price range, ratings, etc.) para i-refine ang results\n5. Sort by relevance, price, o popularity\n\nPwede ring mag-search by voice kung i-tap mo ang microphone icon sa search bar.",
      location:
        "Para mag-change ng location:\n1. Tap sa location text sa taas ng screen (katabi ng OrderKo logo)\n2. Enter your new address o i-allow ang app na i-detect ang current location mo\n3. Select from suggested addresses o pinpoint sa map\n4. Tap 'Confirm' para i-save ang bagong location\n\nAuto-refresh ang app para ipakita ang businesses na available sa new location mo.",
    },
    payment: {
      gcash:
        "Para mag-pay gamit ang GCash:\n1. Sa checkout page, select 'GCash' as payment method\n2. Tap 'Proceed to Payment'\n3. You'll be redirected to GCash app or webpage\n4. Login to your GCash account\n5. Confirm the payment\n6. Wait for confirmation and return to OrderKo\n\nMake sure na sufficient ang balance mo sa GCash account.",
      credit:
        "Para mag-add ng credit card:\n1. Go to Profile > Payment Methods\n2. Tap 'Add Payment Method'\n3. Select 'Credit/Debit Card'\n4. Enter your card details (number, expiry date, CVV)\n5. Enter the billing address\n6. Tap 'Save Card'\n\nYour card will be securely saved for future transactions. We use encryption para protektahan ang card details mo.",
      discount:
        "Sa ngayon, walang specific discount para sa cash payments. Pero may ibang promos na pwede mong ma-avail:\n• First-time user discount: ₱100 off (code: NEWUSER100)\n• Free delivery promo: No minimum order (code: FREEDELIVERY)\n• Weekend special: 15% off (code: WEEKEND15)\n\nCheck regularly ang Promos section para sa latest discounts at offers!",
    },
  }

  // Load stored history or welcome
  function loadHistory() {
    const stored = localStorage.getItem("orderko_messageHistory")
    if (stored) {
      try {
        messageHistory = JSON.parse(stored)
        messageHistory.forEach((msg) => renderMessage(msg.text, msg.isUser, msg.timestamp))
        if (messageHistory.length > 0) hideQuickTopics()
      } catch (e) {
        console.warn("History parse error", e)
        clearConversation()
      }
    } else {
      clearConversation()
    }
  }

  function saveHistory() {
    localStorage.setItem("orderko_messageHistory", JSON.stringify(messageHistory))
    if (userName) localStorage.setItem("orderko_userName", userName)
  }

  // Helpers
  function getCurrentTime() {
    return new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
  }

  function scrollToBottom() {
    chatbotMessages.scrollTop = chatbotMessages.scrollHeight
  }

  function showTypingIndicator() {
    typingIndicator.classList.remove("hidden")
    scrollToBottom()
  }

  function hideTypingIndicator() {
    typingIndicator.classList.add("hidden")
  }

  function toggleChatbot() {
    isOpen = !isOpen
    if (isOpen) {
      chatbotContainer.classList.remove("hidden")
      setTimeout(() => {
        chatbotContainer.classList.add("visible")
        chatbotInput.focus()
        scrollToBottom()

        // Show help popup for first-time users
        if (firstTimeOpen) {
          showHelpPopup()
        }
      }, 50)
    } else {
      chatbotContainer.classList.remove("visible")
      setTimeout(() => {
        chatbotContainer.classList.add("hidden")
      }, 300)
    }
  }

  function showHelpPopup() {
    helpPopup.classList.remove("hidden")
    setTimeout(() => {
      helpPopup.classList.add("visible")
    }, 100)
  }

  function hideHelpPopup() {
    helpPopup.classList.remove("visible")
    setTimeout(() => {
      helpPopup.classList.add("hidden")
    }, 300)

    if (dontShowAgainCheckbox.checked) {
      localStorage.setItem("orderko_firstTimeOpen", "false")
      firstTimeOpen = false
    }
  }

  function hideQuickTopics() {
    if (showingQuickTopics) {
      quickTopics.classList.add("hidden")
      showingQuickTopics = false
    }
  }

  function showQuickTopics() {
    if (!showingQuickTopics) {
      quickTopics.classList.remove("hidden")
      showingQuickTopics = true
    }
  }

  function clearConversation() {
    chatbotMessages.innerHTML = `<div class="chat-day-divider"><span>Today</span></div>`
    messageHistory = []
    currentConversationContext = null
    awaitingResponse = false
    saveHistory()
    showQuickTopics()
    addBotMessage(`Kumusta! 👋 Ako si Gabay, ang OrderKo assistant. Paano kita matutulungan ngayon?`)
  }
function showFeedbackForm() {
    // Create and show feedback form
    const feedbackForm = document.createElement("div")
    feedbackForm.className = "feedback-form"
    feedbackForm.innerHTML = `
      <div class="feedback-form-content">
        <h3>Kumusta ang aming serbisyo?</h3>
        <p>Tulungan mo kaming mapabuti pa ang Koko AI Support</p>
        <div class="feedback-rating">
          <button class="rating-button" data-rating="1">😞</button>
          <button class="rating-button" data-rating="2">🙁</button>
          <button class="rating-button" data-rating="3">😐</button>
          <button class="rating-button" data-rating="4">🙂</button>
          <button class="rating-button" data-rating="5">😃</button>
        </div>
        <textarea id="feedback-text" placeholder="Ano ang pwede naming pagbutihin? (optional)"></textarea>
        <div class="feedback-actions">
          <button id="feedback-cancel">Cancel</button>
          <button id="feedback-submit" disabled>Submit</button>
        </div>
      </div>
    `

    document.body.appendChild(feedbackForm)

    // Add event listeners
    const ratingButtons = feedbackForm.querySelectorAll(".rating-button")
    const submitButton = feedbackForm.querySelector("#feedback-submit")
    const cancelButton = feedbackForm.querySelector("#feedback-cancel")

    let selectedRating = 0

    ratingButtons.forEach((button) => {
      button.addEventListener("click", () => {
        // Remove active class from all buttons
        ratingButtons.forEach((btn) => btn.classList.remove("active"))

        // Add active class to clicked button
        button.classList.add("active")

        // Update selected rating
        selectedRating = Number.parseInt(button.dataset.rating)

        // Enable submit button
        submitButton.disabled = false
      })
    })

    submitButton.addEventListener("click", () => {
      const feedbackText = feedbackForm.querySelector("#feedback-text").value

      // Here you would normally send the feedback to your server
      console.log("Feedback submitted:", { rating: selectedRating, text: feedbackText })

      // Show thank you message
      showToast("Salamat sa iyong feedback!")

      // Remove feedback form
      document.body.removeChild(feedbackForm)
    })

    cancelButton.addEventListener("click", () => {
      // Remove feedback form
      document.body.removeChild(feedbackForm)
    })
  }

function minimizeChatbot() {
  if (!isMinimized) {
    // Save current state
    isMinimized = true
    chatbotContainer.classList.add("minimized")
    minimizeButton.innerHTML = `
 <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
   <polyline points="18 15 12 9 6 15"></polyline>
 </svg>
 `
    minimizeButton.setAttribute("title", "Expand chat")
  } else {
    // Restore state
    isMinimized = false
    chatbotContainer.classList.remove("minimized")
    minimizeButton.innerHTML = `
 <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
   <polyline points="6 9 12 15 18 9"></polyline>
 </svg>
 `
    minimizeButton.setAttribute("title", "Minimize chat")
    scrollToBottom()
  }
}
  function renderMessage(text, isUser, timestamp) {
    const time = new Date(timestamp || Date.now())
    const container = document.createElement("div")
    container.className = "message-container"
    const msgEl = document.createElement("div")
    msgEl.className = `chatbot-message ${isUser ? "chatbot-message-sent" : "chatbot-message-received"}`
    msgEl.setAttribute("aria-label", isUser ? "Your message" : "Bot message")
    const content = document.createElement("div")
    content.className = "message-content"
    content.textContent = text
    const timeEl = document.createElement("div")
    timeEl.className = "message-time"
    timeEl.textContent = time.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
    msgEl.appendChild(content)
    msgEl.appendChild(timeEl)
    container.appendChild(msgEl)
    chatbotMessages.appendChild(container)
    scrollToBottom()
  }
  

  function addMessage(text, isUser = false) {
    const timestamp = new Date().toISOString()
    messageHistory.push({ text, isUser, timestamp })
    saveHistory()
    renderMessage(text, isUser, timestamp)
    hideQuickTopics()
  }

  function addUserMessage(text) {
    addMessage(text, true)
  }

  function addBotMessage(text) {
    addMessage(text, false)
  }

  function showSuggestionChips(suggestions) {
    // Clear previous suggestions
    suggestionChips.innerHTML = ""

    // Add new suggestion chips
    suggestions.forEach((suggestion) => {
      const chip = document.createElement("button")
      chip.className = "suggestion-chip"
      chip.textContent = suggestion
      chip.addEventListener("click", () => {
        // When clicked, send as user message
        addUserMessage(suggestion)
        // Hide suggestions
        hideSuggestionChips()
        // Process the message
        showTypingIndicator()
        setTimeout(
          () => {
            hideTypingIndicator()
            processUserMessage(suggestion)
          },
          Math.random() * 800 + 800,
        )
      })
      suggestionChips.appendChild(chip)
    })

    // Show the suggestions container
    suggestionChips.classList.remove("hidden")
    scrollToBottom()
  }

  function hideSuggestionChips() {
    suggestionChips.classList.add("hidden")
  }

  function sendMessage() {
    const text = chatbotInput.value.trim()
    if (!text) return

    // Hide suggestion chips when user sends a message
    hideSuggestionChips()

    addUserMessage(text)
    chatbotInput.value = ""
    showTypingIndicator()

    // Simulate typing delay
    setTimeout(
      () => {
        hideTypingIndicator()
        processUserMessage(text)
      },
      Math.random() * 800 + 800,
    )
  }

  function processUserMessage(userInput) {
    const input = userInput.toLowerCase()

    // Check for name introduction
    const nameMatch = input.match(/\b(?:ako si|ako ay si|i am|my name is|call me)\s+([a-z]+)/i)
    if (nameMatch) {
      userName = nameMatch[1].charAt(0).toUpperCase() + nameMatch[1].slice(1)
      localStorage.setItem("orderko_userName", userName)
      addBotMessage(
        `Nice to meet you, ${userName}! Ako si Gabay, ang OrderKo assistant. Paano kita matutulungan ngayon?`,
      )
      return
    }

    // Check for thank you
    if (/\b(salamat|thank|thanks|thank you)\b/i.test(input)) {
      addBotMessage("Walang anuman! Masaya akong makatulong. May iba pa ba akong maitutulong sa'yo ngayon?")

      // Show some general suggestion chips
      showSuggestionChips(["Paano mag-order?", "Paano mag-track ng order?", "Anong payment methods ang available?"])
      return
    }

    // Check for goodbye
    if (/\b(paalam|bye|goodbye|see you|hanggang sa muli)\b/i.test(input)) {
      addBotMessage(
        `Salamat sa pag-chat! Bumalik ka lang kung may kailangan ka pa. ${userName ? "Ingat, " + userName + "!" : "Ingat!"}`,
      )
      return
    }

    // Check for specific follow-up responses
    if (currentConversationContext) {
      const context = currentConversationContext

      // Check for specific keywords in the user's message
      if (context === "orderProcess") {
        if (/\b(browse|search|find|hanap|categories)\b/i.test(input)) {
          addBotMessage(specificResponses.orderProcess.browse)
          showSuggestionChips(["Paano mag-filter ng results?", "Paano mag-search by category?", "Salamat sa info!"])
          return
        } else if (/\b(checkout|payment|bayad|pay)\b/i.test(input)) {
          addBotMessage(specificResponses.orderProcess.checkout)
          showSuggestionChips(["Anong payment methods ang available?", "May cash on delivery ba?", "Salamat sa info!"])
          return
        } else if (/\b(advance|pre-?order|schedule)\b/i.test(input)) {
          addBotMessage(specificResponses.orderProcess.advance)
          showSuggestionChips(["Gaano katagal in advance?", "May discount ba sa pre-order?", "Salamat sa info!"])
          return
        }
      } else if (context === "trackOrder") {
        if (/\b(status|meaning|ibig sabihin)\b/i.test(input)) {
          addBotMessage(specificResponses.trackOrder.status)
          showSuggestionChips([
            "Bakit hindi pa updated ang status ko?",
            "Gaano katagal bago ma-deliver?",
            "Salamat sa info!",
          ])
          return
        } else if (/\b(problem|issue|problema|delay|late)\b/i.test(input)) {
          addBotMessage(specificResponses.trackOrder.problem)
          showSuggestionChips(["Paano mag-contact sa rider?", "Pwede bang i-refund?", "Salamat sa info!"])
          return
        } else if (/\b(cancel|i-cancel|cancellation)\b/i.test(input)) {
          addBotMessage(specificResponses.trackOrder.cancel)
          showSuggestionChips(["May cancellation fee ba?", "Gaano katagal ang refund?", "Salamat sa info!"])
          return
        }
      } else if (context === "howToUse") {
        if (/\b(profile|account|settings)\b/i.test(input)) {
          addBotMessage(specificResponses.howToUse.profile)
          showSuggestionChips(["Paano mag-change ng password?", "Paano mag-add ng address?", "Salamat sa info!"])
          return
        } else if (/\b(search|hanap|find)\b/i.test(input)) {
          addBotMessage(specificResponses.howToUse.search)
          showSuggestionChips(["Paano mag-filter ng results?", "Paano mag-sort by price?", "Salamat sa info!"])
          return
        } else if (/\b(location|address|lugar)\b/i.test(input)) {
          addBotMessage(specificResponses.howToUse.location)
          showSuggestionChips([
            "Saan available ang OrderKo?",
            "Bakit limited ang nakikita kong stores?",
            "Salamat sa info!",
          ])
          return
        }
      } else if (context === "payment") {
        if (/\b(gcash|g-cash)\b/i.test(input)) {
          addBotMessage(specificResponses.payment.gcash)
          showSuggestionChips(["May transaction fee ba?", "Paano kung failed ang GCash payment?", "Salamat sa info!"])
          return
        } else if (/\b(credit|card|credit card|debit)\b/i.test(input)) {
          addBotMessage(specificResponses.payment.credit)
          showSuggestionChips(["Secure ba ang card details ko?", "Anong cards ang accepted?", "Salamat sa info!"])
          return
        } else if (/\b(discount|less|bawas|promo)\b/i.test(input)) {
          addBotMessage(specificResponses.payment.discount)
          showSuggestionChips(["Paano gamitin ang promo code?", "May loyalty program ba?", "Salamat sa info!"])
          return
        }
      }
    }

    // Process based on keywords
    if (/\b(order|mag-order|umorder|checkout|place order)\b/i.test(input)) {
      handleCategoryResponse("orderProcess")
      return
    }

    if (/\b(track|status|nasaan|where is|order status|tracking)\b/i.test(input)) {
      handleCategoryResponse("trackOrder")
      return
    }

    if (/\b(paano gamitin|how to use|tutorial|guide|instructions)\b/i.test(input)) {
      handleCategoryResponse("howToUse")
      return
    }

    if (/\b(business|store|shop|tindahan|restaurant|bakery|clothing)\b/i.test(input)) {
      handleCategoryResponse("businessInfo")
      return
    }

    if (/\b(contact|support|help desk|customer service|tulong)\b/i.test(input)) {
      handleCategoryResponse("contactSupport")
      return
    }

    if (/\b(payment|bayad|pay|gcash|credit card|cod|cash)\b/i.test(input)) {
      handleCategoryResponse("payment")
      return
    }

    if (/\b(delivery|deliver|shipping|ship|courier|pickup|kuha)\b/i.test(input)) {
      handleCategoryResponse("delivery")
      return
    }

    if (/\b(account|profile|user|login|sign up|register)\b/i.test(input)) {
      handleCategoryResponse("account")
      return
    }

    if (/\b(category|categories|food|clothing|crafts|bakery)\b/i.test(input)) {
      handleCategoryResponse("categories")
      return
    }

    if (/\b(pre-?order|advance order|order ahead|schedule)\b/i.test(input)) {
      handleCategoryResponse("preOrder")
      return
    }

    if (/\b(featured|popular|best seller|recommended|suggestion)\b/i.test(input)) {
      handleCategoryResponse("featuredBusinesses")
      return
    }

    if (/\b(location|address|area|deliver to|manila|philippines)\b/i.test(input)) {
      handleCategoryResponse("location")
      return
    }

    if (/\b(promo|discount|voucher|code|sale|free delivery)\b/i.test(input)) {
      handleCategoryResponse("promos")
      return
    }

    // If no specific category is matched
    addBotMessage(
      `Pasensya, hindi ko masyadong naintindihan ang tanong mo. Pwede mo bang i-clarify? Pwede kang magtanong tungkol sa pag-order, tracking, businesses, payment methods, o delivery options.`,
    )

    // Show suggestion chips for common topics
    showSuggestionChips(["Paano mag-order?", "Paano mag-track ng order?", "Anong payment methods ang available?"])

    // Suggest some topics
    setTimeout(() => {
      showQuickTopics()
    }, 1000)
  }

  function handleCategoryResponse(category) {
    const response = knowledgeBase[category]
    addBotMessage(response.main)

    // Set context for follow-up
    currentConversationContext = category
    lastMessageCategory = category

    // Show suggestion chips
    if (response.suggestions && response.suggestions.length > 0) {
      showSuggestionChips(response.suggestions)
    }
  }

  // Quick topics
  function handleQuickTopic(topic) {
    const mappings = {
      "how-to-order": ["Paano mag-order sa OrderKo?", "orderProcess"],
      track: ["Paano i-track ang order ko?", "trackOrder"],
      "how-to-use": ["Paano gamitin ang OrderKo app?", "howToUse"],
      "business-info": ["Anong businesses ang available sa OrderKo?", "businessInfo"],
      payment: ["Anong payment methods ang available sa OrderKo?", "payment"],
      contact: ["Paano mag-contact sa OrderKo support?", "contactSupport"],
    }

    const [question, category] = mappings[topic] || ["Kailangan ko ng tulong sa OrderKo.", "howToUse"]

    // Hide suggestion chips when selecting a quick topic
    hideSuggestionChips()

    addUserMessage(question)
    showTypingIndicator()

    setTimeout(
      () => {
        hideTypingIndicator()
        handleCategoryResponse(category)
      },
      Math.random() * 800 + 800,
    )
  }

  // Events
  chatbotToggle.addEventListener("click", toggleChatbot)
  chatbotClose.addEventListener("click", toggleChatbot)
  chatbotClear.addEventListener("click", clearConversation)
  chatbotSend.addEventListener("click", sendMessage)
  chatbotInput.addEventListener("keypress", (e) => {
    if (e.key === "Enter") sendMessage()
  })
  chatbotInput.addEventListener("input", () => {
    chatbotSend.disabled = chatbotInput.value.trim() === ""
  })
  quickTopicButtons.forEach((btn) => btn.addEventListener("click", () => handleQuickTopic(btn.dataset.topic)))
  helpCloseButton.addEventListener("click", hideHelpPopup)

  // Add event listeners for new features
  if (minimizeButton) {
    minimizeButton.addEventListener("click", minimizeChatbot)
  }
  
  if (feedbackButton) {
    feedbackButton.addEventListener("click", showFeedbackForm)
  }
  // Initialize
  chatbotSend.disabled = true
  setTimeout(loadHistory, 200)
})()
