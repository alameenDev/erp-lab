<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();
const lang = computed(() => localStorage.getItem("locale") || "ar");

// Scroll state for sticky nav
const scrolled = ref(false);
const onScroll = () => { scrolled.value = window.scrollY > 20; };
onMounted(() => window.addEventListener("scroll", onScroll, { passive: true }));
onUnmounted(() => window.removeEventListener("scroll", onScroll));

// Live ticker for hero: rotating recent-test feed
const tickerItems = [
  { code: "CBC", patient: "A.M.", status: "done", color: "success" },
  { code: "TSH", patient: "S.K.", status: "high", color: "warning" },
  { code: "FBS", patient: "K.R.", status: "done", color: "success" },
  { code: "HbA1c", patient: "N.H.", status: "low", color: "danger" },
  { code: "Vit D", patient: "M.A.", status: "done", color: "success" },
  { code: "LFT", patient: "Z.J.", status: "done", color: "success" },
];
const tickerIndex = ref(0);
let tickerTimer;
onMounted(() => { tickerTimer = setInterval(() => { tickerIndex.value = (tickerIndex.value + 1) % tickerItems.length; }, 2200); });
onUnmounted(() => clearInterval(tickerTimer));

// FAQ state
const openFaq = ref(null);
const toggleFaq = (index) => {
  openFaq.value = openFaq.value === index ? null : index;
};

// Feature card expand state (for mobile)
const expandedFeature = ref(null);
const toggleFeature = (index) => {
  expandedFeature.value = expandedFeature.value === index ? null : index;
};

const t = (key) => {
  const translations = {
    en: {
      // Nav
      nav_brand: "Digital Lab",
      nav_tagline: "Laboratory Management",
      nav_features: "Features",
      nav_how_it_works: "How it Works",
      nav_testimonials: "Testimonials",
      nav_faq: "FAQ",
      nav_contact: "Contact",
      nav_login: "Sign In",
      nav_register: "Register Lab",
      language: "Language",

      // Hero
      hero_badge: "Trusted by 500+ Medical Laboratories",
      hero_title_1: "Transform Your",
      hero_title_2: "Laboratory Management",
      hero_title_3: "Experience",
      hero_description: "The most comprehensive laboratory information management system. Streamline operations, enhance accuracy, and deliver results faster with our cutting-edge platform.",
      hero_cta_primary: "Start Free Trial",
      hero_cta_secondary: "Watch Demo",
      hero_trusted: "Trusted by leading laboratories",
      hero_live: "LIVE",
      hero_iso: "ISO 15189 Ready",
      hero_hipaa: "HIPAA Compliant",
      hero_uptime: "99.9% Uptime SLA",
      hero_feed_title: "Live Lab Feed",
      hero_feed_url: "digital-labs.com/dashboard",
      hero_kpi_tests_today: "Tests Today",
      hero_kpi_pending: "Pending",
      hero_kpi_in_queue: "In Queue",
      hero_kpi_accuracy: "Accuracy",
      hero_kpi_excellent: "Excellent",
      hero_recent_results: "Recent Results",
      hero_auto_refresh: "auto-refresh",
      hero_just_now: "just now",
      hero_minutes_ago: "{n}m ago",
      hero_chart_title: "7-Day Volume",
      hero_result_delivered: "Result Delivered",
      hero_via_whatsapp: "via WhatsApp · 2s ago",
      hero_labs_online: "+342 Labs Online",
      hero_across_region: "across the region",

      // Stats
      stat_labs: "Active Labs",
      stat_tests: "Tests/Month",
      stat_patients: "Patients Served",
      stat_accuracy: "Accuracy Rate",

      // Features
      features_badge: "Powerful Features",
      features_title: "Everything You Need to",
      features_title_highlight: "Run Your Lab Efficiently",
      features_description: "Our comprehensive suite of tools helps you manage every aspect of your laboratory operations.",
      feature_response_time: "Response Time",

      feature_1_title: "Patient Management",
      feature_1_desc: "Complete patient records with medical history, demographics, and seamless registration. Track patient journeys from registration to result delivery.",

      feature_2_title: "Test Processing",
      feature_2_desc: "Configure unlimited tests with reference ranges, formulas, and automated calculations. Support for all test types including cultures and panels.",

      feature_3_title: "Smart Invoicing",
      feature_3_desc: "Generate professional invoices, track payments, manage contracts, and handle multiple price lists with ease.",

      feature_4_title: "Medical Reports",
      feature_4_desc: "Create professional reports with customizable templates, digital signatures, QR verification, and instant delivery via WhatsApp or email.",

      feature_5_title: "Analytics Dashboard",
      feature_5_desc: "Real-time insights with comprehensive dashboards. Track revenue, test volumes, turnaround times, and make data-driven decisions.",

      feature_6_title: "Role-Based Access",
      feature_6_desc: "Granular permission system with 50+ permissions. Control who can access what with complete audit trails.",

      equipment_integration: "We have integration with laboratory equipment",
      equipment_integration_desc: "Seamlessly connect your analyzers and lab instruments for automated result transfer and reduced manual errors.",
      equipment_integration_badge: "NEW",

      youtube_title: "Learn How to Use the Platform",
      youtube_desc: "Watch our video tutorials to get the most out of the system",
      youtube_cta: "Watch Tutorials",
      youtube_label: "TUTORIALS",

      // How it Works
      how_badge: "Simple Process",
      how_title: "Get Started in",
      how_title_highlight: "3 Easy Steps",
      how_description: "Our streamlined onboarding process gets your lab up and running quickly.",

      how_step_1_title: "Create Account",
      how_step_1_desc: "Sign up in minutes and configure your laboratory profile with basic information.",

      how_step_2_title: "Setup Tests & Users",
      how_step_2_desc: "Import your test catalog, set reference ranges, and invite your team members.",

      how_step_3_title: "Start Processing",
      how_step_3_desc: "Begin registering patients, processing tests, and delivering results instantly.",

      // Testimonials
      testimonials_badge: "Customer Stories",
      testimonials_title: "Loved by",
      testimonials_title_highlight: "Laboratory Professionals",
      testimonials_description: "See what our customers say about transforming their laboratory operations.",
      testimonials_rating_label: "· based on 500+ reviews",
      testimonials_featured: "FEATURED",
      faq_support_subtitle: "Our team is ready to help",

      testimonial_1_text: "This system has revolutionized how we manage our laboratory. The automation features alone have saved us countless hours every week.",
      testimonial_1_name: "Dr. Ahmed Al-Rashid",
      testimonial_1_role: "Lab Director, Central Medical Lab",

      testimonial_2_text: "The reporting capabilities are exceptional. Our patients love receiving their results instantly via WhatsApp with QR verification.",
      testimonial_2_name: "Dr. Sarah Hassan",
      testimonial_2_role: "Chief Pathologist, City Diagnostics",

      testimonial_3_text: "Finally, a system that understands the needs of modern laboratories. The Arabic support and RTL interface are perfect for our region.",
      testimonial_3_name: "Dr. Mohammed Ali",
      testimonial_3_role: "Owner, Premium Labs Network",

      // Pricing
      nav_pricing: "Pricing",
      pricing_badge: "Simple Pricing",
      pricing_title: "Choose the Perfect",
      pricing_title_highlight: "Plan for Your Lab",
      pricing_description: "Transparent pricing with no hidden fees. Start with what you need and scale as you grow.",

      pricing_currency: "IQD",

      pricing_plan_1_name: "Basic",
      pricing_plan_1_users: "1 user",
      pricing_plan_1_monthly: "10,000",
      pricing_plan_1_annual: "100,000",
      pricing_plan_1_f1: "Patient management",
      pricing_plan_1_f2: "Test processing",
      pricing_plan_1_f3: "Basic reports",
      pricing_plan_1_f4: "Email support",
      pricing_plan_1_cta: "Get Started",

      pricing_plan_2_name: "Standard",
      pricing_plan_2_users: "2 users",
      pricing_plan_2_monthly: "15,000",
      pricing_plan_2_annual: "150,000",
      pricing_plan_2_f1: "Everything in Basic",
      pricing_plan_2_f2: "Smart invoicing",
      pricing_plan_2_f3: "WhatsApp delivery",
      pricing_plan_2_f4: "Priority support",
      pricing_plan_2_cta: "Get Started",

      pricing_plan_3_name: "Professional",
      pricing_plan_3_users: "5 users",
      pricing_plan_3_monthly: "35,000",
      pricing_plan_3_annual: "350,000",
      pricing_plan_3_f1: "Everything in Standard",
      pricing_plan_3_f2: "Analytics dashboard",
      pricing_plan_3_f3: "Custom templates",
      pricing_plan_3_f4: "API access",
      pricing_plan_3_cta: "Get Started",

      pricing_plan_4_name: "Unlimited",
      pricing_plan_4_users: "Unlimited users",
      pricing_plan_4_monthly: "75,000",
      pricing_plan_4_annual: "750,000",
      pricing_plan_4_f1: "Everything in Professional",
      pricing_plan_4_f2: "Unlimited branches",
      pricing_plan_4_f3: "Dedicated manager",
      pricing_plan_4_f4: "24/7 phone support",
      pricing_plan_4_cta: "Get Started",
      pricing_plan_4_popular: "Most Popular",

      pricing_plan_5_name: "Enterprise",
      pricing_plan_5_users: "Custom",
      pricing_plan_5_desc: "Need a tailored solution for your laboratory network? We offer custom configurations, dedicated infrastructure, and premium support.",
      pricing_plan_5_branch_note: "+ 75,000 IQD per additional branch",
      pricing_plan_5_f1: "Everything in Unlimited",
      pricing_plan_5_f2: "Custom integrations",
      pricing_plan_5_f3: "SLA guarantee",
      pricing_plan_5_f4: "On-site training",
      pricing_plan_5_cta: "Contact Us",

      pricing_per_month: "/mo",
      pricing_per_year: "/yr",

      // Dashboard mockup
      weekly_tests: "Weekly Tests",
      recent_results: "Recent Results",
      completed: "Completed",
      processing: "Processing",
      result_delivered: "Result Delivered",
      sent_via_whatsapp: "Sent via WhatsApp",
      this_week: "This Week",
      increase: "increase",
      tap_for_details: "Tap for details",
      didnt_find_answer: "Didn't find your answer?",
      contact_support: "Contact Support",

      // FAQ
      faq_badge: "Common Questions",
      faq_title: "Frequently Asked",
      faq_title_highlight: "Questions",
      faq_description: "Find answers to common questions about our laboratory management system.",

      faq_1_q: "How long does it take to set up the system?",
      faq_1_a: "Most laboratories are fully operational within 24-48 hours. Our team provides comprehensive onboarding support, including data migration, test configuration, and staff training.",

      faq_2_q: "Is my data secure and compliant?",
      faq_2_a: "Absolutely. We use enterprise-grade encryption, regular backups, and comply with healthcare data protection standards. Your data is stored securely with role-based access controls.",

      faq_3_q: "Can I import my existing patient and test data?",
      faq_3_a: "Yes! We support bulk import from Excel, CSV, and most legacy systems. Our team will assist you with data migration to ensure a smooth transition.",

      faq_4_q: "Do you support multiple laboratory branches?",
      faq_4_a: "Yes, our system is designed for multi-branch operations. You can manage multiple locations with centralized reporting and branch-specific configurations.",

      faq_5_q: "What kind of support do you provide?",
      faq_5_a: "We offer 24/7 technical support via chat, email, and phone. Premium plans include a dedicated account manager and priority support.",

      faq_6_q: "Can I customize reports and invoices?",
      faq_6_a: "Yes, you have full control over report templates, invoice formats, and branding. Add your logo, customize layouts, and create department-specific templates.",

      // CTA
      cta_badge: "Get Started Today",
      cta_title: "Ready to Transform",
      cta_title_highlight: "Your Laboratory?",
      cta_description: "Join hundreds of laboratories that have modernized their operations. Start your free trial today - no credit card required.",
      cta_primary: "Start Free Trial",
      cta_secondary: "Schedule Demo",
      cta_feature_1: "14-day free trial",
      cta_feature_2: "No credit card required",
      cta_feature_3: "Full feature access",
      cta_feature_4: "Cancel anytime",

      // Footer
      footer_description: "Comprehensive medical laboratory management system designed for modern healthcare facilities. Streamline operations and deliver excellence.",
      footer_product: "Product",
      footer_features: "Features",
      footer_pricing: "Pricing",
      footer_updates: "Updates",
      footer_company: "Company",
      footer_about: "About Us",
      footer_careers: "Careers",
      footer_contact: "Contact",
      footer_resources: "Resources",
      footer_docs: "Documentation",
      footer_help: "Help Center",
      footer_api: "API Reference",
      footer_legal: "Legal",
      footer_privacy: "Privacy Policy",
      footer_terms: "Terms of Service",
      footer_rights: "All rights reserved.",
      footer_made_with: "Made with",
      footer_for: "for laboratories worldwide",
    },
    ar: {
      // Nav
      nav_brand: "المختبر الرقمي",
      nav_tagline: "إدارة المختبرات",
      nav_features: "المميزات",
      nav_how_it_works: "كيف يعمل",
      nav_testimonials: "آراء العملاء",
      nav_faq: "الأسئلة الشائعة",
      nav_contact: "اتصل بنا",
      nav_login: "تسجيل الدخول",
      nav_register: "تسجيل مختبر",
      language: "اللغة",

      // Hero
      hero_badge: "موثوق من قبل 500+ مختبر طبي",
      hero_title_1: "حوّل تجربة",
      hero_title_2: "إدارة مختبرك",
      hero_title_3: "الطبي",
      hero_description: "نظام إدارة معلومات المختبرات الأكثر شمولاً. قم بتبسيط العمليات وتعزيز الدقة وتقديم النتائج بشكل أسرع مع منصتنا المتطورة.",
      hero_cta_primary: "ابدأ التجربة المجانية",
      hero_cta_secondary: "شاهد العرض التوضيحي",
      hero_trusted: "موثوق من المختبرات الرائدة",
      hero_live: "مباشر",
      hero_iso: "متوافق مع ISO 15189",
      hero_hipaa: "متوافق مع HIPAA",
      hero_uptime: "ضمان جاهزية 99.9%",
      hero_feed_title: "البث المباشر للمختبر",
      hero_feed_url: "digital-labs.com/dashboard",
      hero_kpi_tests_today: "فحوصات اليوم",
      hero_kpi_pending: "قيد الانتظار",
      hero_kpi_in_queue: "في الانتظار",
      hero_kpi_accuracy: "الدقة",
      hero_kpi_excellent: "ممتاز",
      hero_recent_results: "أحدث النتائج",
      hero_auto_refresh: "تحديث تلقائي",
      hero_just_now: "الآن",
      hero_minutes_ago: "قبل {n} دقيقة",
      hero_chart_title: "حجم 7 أيام",
      hero_result_delivered: "تم تسليم النتيجة",
      hero_via_whatsapp: "عبر واتساب · قبل ٢ ثانية",
      hero_labs_online: "+342 مختبر متصل",
      hero_across_region: "في جميع أنحاء المنطقة",

      // Stats
      stat_labs: "مختبر نشط",
      stat_tests: "فحص/شهرياً",
      stat_patients: "مريض تمت خدمته",
      stat_accuracy: "نسبة الدقة",

      // Features
      features_badge: "مميزات قوية",
      features_title: "كل ما تحتاجه",
      features_title_highlight: "لإدارة مختبرك بكفاءة",
      features_description: "مجموعة شاملة من الأدوات تساعدك على إدارة جميع جوانب عمليات مختبرك.",
      feature_response_time: "زمن الاستجابة",

      feature_1_title: "إدارة المرضى",
      feature_1_desc: "سجلات مرضى كاملة مع التاريخ الطبي والبيانات الديموغرافية والتسجيل السلس. تتبع رحلة المريض من التسجيل حتى تسليم النتائج.",

      feature_2_title: "معالجة الفحوصات",
      feature_2_desc: "تكوين فحوصات غير محدودة مع النطاقات المرجعية والمعادلات والحسابات الآلية. دعم لجميع أنواع الفحوصات بما في ذلك المزارع والباقات.",

      feature_3_title: "فوترة ذكية",
      feature_3_desc: "إنشاء فواتير احترافية وتتبع المدفوعات وإدارة العقود والتعامل مع قوائم أسعار متعددة بسهولة.",

      feature_4_title: "التقارير الطبية",
      feature_4_desc: "إنشاء تقارير احترافية مع قوالب قابلة للتخصيص وتوقيعات رقمية والتحقق عبر QR والتسليم الفوري عبر واتساب أو البريد الإلكتروني.",

      feature_5_title: "لوحة التحليلات",
      feature_5_desc: "رؤى في الوقت الفعلي مع لوحات معلومات شاملة. تتبع الإيرادات وأحجام الفحوصات وأوقات التسليم واتخذ قرارات مبنية على البيانات.",

      feature_6_title: "صلاحيات متقدمة",
      feature_6_desc: "نظام صلاحيات دقيق مع 50+ صلاحية. تحكم في من يمكنه الوصول إلى ماذا مع سجلات تدقيق كاملة.",

      equipment_integration: "لدينا تكامل مع أجهزة المختبر",
      equipment_integration_desc: "اربط أجهزة التحليل والمعدات المخبرية بسلاسة لنقل النتائج تلقائياً وتقليل الأخطاء اليدوية.",
      equipment_integration_badge: "جديد",
      youtube_label: "دروس",
      testimonials_rating_label: "· بناءً على ٥٠٠+ تقييم",
      testimonials_featured: "مميز",
      faq_support_subtitle: "فريقنا جاهز لمساعدتك",

      youtube_title: "تعلّم كيفية استخدام المنصة",
      youtube_desc: "شاهد الفيديوهات التعليمية للاستفادة القصوى من النظام",
      youtube_cta: "شاهد الشروحات",

      // How it Works
      how_badge: "عملية بسيطة",
      how_title: "ابدأ في",
      how_title_highlight: "3 خطوات سهلة",
      how_description: "عملية الإعداد المبسطة لدينا تجعل مختبرك يعمل بسرعة.",

      how_step_1_title: "إنشاء حساب",
      how_step_1_desc: "سجل في دقائق وقم بتكوين ملف مختبرك بالمعلومات الأساسية.",

      how_step_2_title: "إعداد الفحوصات والمستخدمين",
      how_step_2_desc: "استورد قائمة فحوصاتك وحدد النطاقات المرجعية وادعُ أعضاء فريقك.",

      how_step_3_title: "ابدأ المعالجة",
      how_step_3_desc: "ابدأ بتسجيل المرضى ومعالجة الفحوصات وتسليم النتائج فوراً.",

      // Testimonials
      testimonials_badge: "قصص العملاء",
      testimonials_title: "محبوب من",
      testimonials_title_highlight: "المتخصصين في المختبرات",
      testimonials_description: "اطلع على ما يقوله عملاؤنا عن تحويل عمليات مختبراتهم.",

      testimonial_1_text: "هذا النظام أحدث ثورة في طريقة إدارتنا لمختبرنا. ميزات الأتمتة وحدها وفرت لنا ساعات لا تحصى كل أسبوع.",
      testimonial_1_name: "د. أحمد الراشد",
      testimonial_1_role: "مدير المختبر، المختبر الطبي المركزي",

      testimonial_2_text: "إمكانيات التقارير استثنائية. مرضانا يحبون استلام نتائجهم فوراً عبر واتساب مع التحقق بـ QR.",
      testimonial_2_name: "د. سارة حسن",
      testimonial_2_role: "رئيس قسم التحاليل، سيتي دايجنوستكس",

      testimonial_3_text: "أخيراً، نظام يفهم احتياجات المختبرات الحديثة. دعم العربية وواجهة RTL مثالية لمنطقتنا.",
      testimonial_3_name: "د. محمد علي",
      testimonial_3_role: "مالك، شبكة مختبرات بريميوم",

      // Pricing
      nav_pricing: "الأسعار",
      pricing_badge: "أسعار بسيطة",
      pricing_title: "اختر الخطة",
      pricing_title_highlight: "المثالية لمختبرك",
      pricing_description: "أسعار شفافة بدون رسوم مخفية. ابدأ بما تحتاجه وتوسع مع نموك.",

      pricing_currency: "د.ع",

      pricing_plan_1_name: "الأساسية",
      pricing_plan_1_users: "مستخدم واحد",
      pricing_plan_1_monthly: "10,000",
      pricing_plan_1_annual: "100,000",
      pricing_plan_1_f1: "إدارة المرضى",
      pricing_plan_1_f2: "معالجة الفحوصات",
      pricing_plan_1_f3: "تقارير أساسية",
      pricing_plan_1_f4: "دعم بالبريد الإلكتروني",
      pricing_plan_1_cta: "ابدأ الآن",

      pricing_plan_2_name: "القياسية",
      pricing_plan_2_users: "مستخدمان",
      pricing_plan_2_monthly: "15,000",
      pricing_plan_2_annual: "150,000",
      pricing_plan_2_f1: "كل مميزات الأساسية",
      pricing_plan_2_f2: "فوترة ذكية",
      pricing_plan_2_f3: "إرسال عبر واتساب",
      pricing_plan_2_f4: "دعم ذو أولوية",
      pricing_plan_2_cta: "ابدأ الآن",

      pricing_plan_3_name: "الاحترافية",
      pricing_plan_3_users: "5 مستخدمين",
      pricing_plan_3_monthly: "35,000",
      pricing_plan_3_annual: "350,000",
      pricing_plan_3_f1: "كل مميزات القياسية",
      pricing_plan_3_f2: "لوحة التحليلات",
      pricing_plan_3_f3: "قوالب مخصصة",
      pricing_plan_3_f4: "وصول API",
      pricing_plan_3_cta: "ابدأ الآن",

      pricing_plan_4_name: "غير محدودة",
      pricing_plan_4_users: "مستخدمون غير محدودين",
      pricing_plan_4_monthly: "75,000",
      pricing_plan_4_annual: "750,000",
      pricing_plan_4_f1: "كل مميزات الاحترافية",
      pricing_plan_4_f2: "فروع غير محدودة",
      pricing_plan_4_f3: "مدير حساب مخصص",
      pricing_plan_4_f4: "دعم هاتفي 24/7",
      pricing_plan_4_cta: "ابدأ الآن",
      pricing_plan_4_popular: "الأكثر شعبية",

      pricing_plan_5_name: "المؤسسات",
      pricing_plan_5_users: "مخصص",
      pricing_plan_5_desc: "هل تحتاج إلى حل مخصص لشبكة مختبراتك؟ نقدم تكوينات مخصصة وبنية تحتية مخصصة ودعم متميز.",
      pricing_plan_5_branch_note: "+ 75,000 د.ع لكل فرع إضافي",
      pricing_plan_5_f1: "كل مميزات غير محدودة",
      pricing_plan_5_f2: "تكاملات مخصصة",
      pricing_plan_5_f3: "ضمان SLA",
      pricing_plan_5_f4: "تدريب في الموقع",
      pricing_plan_5_cta: "اتصل بنا",

      pricing_per_month: "/شهر",
      pricing_per_year: "/سنة",

      // Dashboard mockup
      weekly_tests: "الفحوصات الأسبوعية",
      recent_results: "أحدث النتائج",
      completed: "مكتمل",
      processing: "قيد المعالجة",
      result_delivered: "تم تسليم النتيجة",
      sent_via_whatsapp: "تم الإرسال عبر واتساب",
      this_week: "هذا الأسبوع",
      increase: "زيادة",
      tap_for_details: "انقر للمزيد",
      didnt_find_answer: "لم تجد إجابتك؟",
      contact_support: "تواصل مع الدعم",

      // FAQ
      faq_badge: "أسئلة شائعة",
      faq_title: "الأسئلة",
      faq_title_highlight: "المتكررة",
      faq_description: "اعثر على إجابات للأسئلة الشائعة حول نظام إدارة المختبرات لدينا.",

      faq_1_q: "كم من الوقت يستغرق إعداد النظام؟",
      faq_1_a: "معظم المختبرات تعمل بشكل كامل خلال 24-48 ساعة. فريقنا يقدم دعم إعداد شامل، بما في ذلك ترحيل البيانات وتكوين الفحوصات وتدريب الموظفين.",

      faq_2_q: "هل بياناتي آمنة ومتوافقة؟",
      faq_2_a: "بالتأكيد. نستخدم تشفيراً على مستوى المؤسسات ونسخاً احتياطية منتظمة ونلتزم بمعايير حماية بيانات الرعاية الصحية. بياناتك مخزنة بأمان مع تحكم في الوصول حسب الأدوار.",

      faq_3_q: "هل يمكنني استيراد بيانات المرضى والفحوصات الحالية؟",
      faq_3_a: "نعم! ندعم الاستيراد الجماعي من Excel و CSV ومعظم الأنظمة القديمة. فريقنا سيساعدك في ترحيل البيانات لضمان انتقال سلس.",

      faq_4_q: "هل تدعمون فروع مختبرات متعددة؟",
      faq_4_a: "نعم، نظامنا مصمم للعمليات متعددة الفروع. يمكنك إدارة مواقع متعددة مع تقارير مركزية وتكوينات خاصة بكل فرع.",

      faq_5_q: "ما نوع الدعم الذي تقدمونه؟",
      faq_5_a: "نقدم دعماً فنياً على مدار الساعة طوال أيام الأسبوع عبر الدردشة والبريد الإلكتروني والهاتف. الخطط المميزة تتضمن مدير حساب مخصص ودعم ذو أولوية.",

      faq_6_q: "هل يمكنني تخصيص التقارير والفواتير؟",
      faq_6_a: "نعم، لديك تحكم كامل في قوالب التقارير وتنسيقات الفواتير والعلامة التجارية. أضف شعارك وخصص التخطيطات وأنشئ قوالب خاصة بكل قسم.",

      // CTA
      cta_badge: "ابدأ اليوم",
      cta_title: "هل أنت مستعد لتحويل",
      cta_title_highlight: "مختبرك؟",
      cta_description: "انضم إلى مئات المختبرات التي حدّثت عملياتها. ابدأ تجربتك المجانية اليوم - لا حاجة لبطاقة ائتمان.",
      cta_primary: "ابدأ التجربة المجانية",
      cta_secondary: "جدولة عرض توضيحي",
      cta_feature_1: "تجربة مجانية 14 يوماً",
      cta_feature_2: "لا حاجة لبطاقة ائتمان",
      cta_feature_3: "وصول كامل للمميزات",
      cta_feature_4: "إلغاء في أي وقت",

      // Footer
      footer_description: "نظام إدارة مختبرات طبية شامل مصمم للمرافق الصحية الحديثة. قم بتبسيط العمليات وتقديم التميز.",
      footer_product: "المنتج",
      footer_features: "المميزات",
      footer_pricing: "الأسعار",
      footer_updates: "التحديثات",
      footer_company: "الشركة",
      footer_about: "من نحن",
      footer_careers: "الوظائف",
      footer_contact: "اتصل بنا",
      footer_resources: "الموارد",
      footer_docs: "التوثيق",
      footer_help: "مركز المساعدة",
      footer_api: "مرجع API",
      footer_legal: "قانوني",
      footer_privacy: "سياسة الخصوصية",
      footer_terms: "شروط الخدمة",
      footer_rights: "جميع الحقوق محفوظة.",
      footer_made_with: "صنع بـ",
      footer_for: "للمختبرات حول العالم",
    },
    ku: {
      // Nav
      nav_brand: "لابی دیجیتاڵ",
      nav_tagline: "بەڕێوەبردنی تاقیگە",
      nav_features: "تایبەتمەندییەکان",
      nav_how_it_works: "چۆن کاردەکات",
      nav_testimonials: "بۆچوونی کڕیاران",
      nav_faq: "پرسیارە باوەکان",
      nav_contact: "پەیوەندیمان پێوە بکە",
      nav_login: "چوونەژوورەوە",
      nav_register: "تۆمارکردنی تاقیگە",
      language: "زمان",

      // Hero
      hero_badge: "متمانەپێکراو لەلایەن 500+ تاقیگەی پزیشکی",
      hero_title_1: "گۆڕینی ئەزموونی",
      hero_title_2: "بەڕێوەبردنی تاقیگەکەت",
      hero_title_3: "بە شێوەیەکی دیجیتاڵ",
      hero_description: "تەواوترین سیستەمی بەڕێوەبردنی زانیاریی تاقیگە. کارەکان ئاسانتر بکە، وردی زیاد بکە، و ئەنجامەکان خێراتر بگەیەنە بە پلاتفۆرمی پێشکەوتووی ئێمە.",
      hero_cta_primary: "دەستپێکردنی تاقیکردنەوەی بێبەرامبەر",
      hero_cta_secondary: "سەیرکردنی دیمۆ",
      hero_trusted: "متمانەپێکراو لەلایەن تاقیگە پێشەنگەکان",
      hero_live: "ڕاستەوخۆ",
      hero_iso: "گونجاو لەگەڵ ISO 15189",
      hero_hipaa: "گونجاو لەگەڵ HIPAA",
      hero_uptime: "گەرەنتی 99.9% ئامادەیی",
      hero_feed_title: "بەشداری ڕاستەوخۆی تاقیگە",
      hero_feed_url: "digital-labs.com/dashboard",
      hero_kpi_tests_today: "تاقیکردنەوەی ئەمڕۆ",
      hero_kpi_pending: "چاوەڕێ",
      hero_kpi_in_queue: "لە ڕیز",
      hero_kpi_accuracy: "وردی",
      hero_kpi_excellent: "نایاب",
      hero_recent_results: "نوێترین ئەنجامەکان",
      hero_auto_refresh: "نوێکردنەوەی خۆکار",
      hero_just_now: "ئێستا",
      hero_minutes_ago: "{n} خولەک پێش",
      hero_chart_title: "قەبارەی 7 ڕۆژ",
      hero_result_delivered: "ئەنجام گەیشت",
      hero_via_whatsapp: "بە ڕێگەی واتساپ · 2 چرکە پێش",
      hero_labs_online: "+342 تاقیگەی چالاک",
      hero_across_region: "لە سەرانسەری ناوچەکە",

      // Stats
      stat_labs: "تاقیگەی چالاک",
      stat_tests: "تاقیکردنەوە/مانگانە",
      stat_patients: "نەخۆش خزمەتکراو",
      stat_accuracy: "ڕێژەی وردی",

      // Features
      features_badge: "تایبەتمەندییە بەهێزەکان",
      features_title: "هەموو ئەوەی پێویستت پێیە بۆ",
      features_title_highlight: "بەڕێوەبردنی تاقیگەکەت بە کاریگەری",
      features_description: "کۆمەڵەیەکی تەواو لە ئامرازەکان یارمەتیت دەدات لە بەڕێوەبردنی هەموو لایەنەکانی کاری تاقیگەکەت.",
      feature_response_time: "کاتی وەڵامدانەوە",

      feature_1_title: "بەڕێوەبردنی نەخۆشەکان",
      feature_1_desc: "تۆماری تەواوی نەخۆشەکان بە مێژووی پزیشکی و زانیاری کەسی و تۆمارکردنی ئاسان. شوێنکەوتنی گەشتی نەخۆش لە تۆمارکردنەوە تاوەکو گەیاندنی ئەنجام.",

      feature_2_title: "پرۆسێسکردنی تاقیکردنەوەکان",
      feature_2_desc: "دانانی تاقیکردنەوەی بێسنوور بە نرخی سەرچاوە و فۆرمولا و ژمارەکردنی ئۆتۆماتیکی. پشتیوانی هەموو جۆرەکانی تاقیکردنەوە بە گەل کەلتوور و پاکێج.",

      feature_3_title: "پسوولەی زیرەک",
      feature_3_desc: "دروستکردنی پسوولەی پیشەیی، شوێنکەوتنی پارەدان، بەڕێوەبردنی گرێبەستەکان، و مامەڵەکردن بە لیستی نرخی جیاجیا بە ئاسانی.",

      feature_4_title: "ڕاپۆرتەکانی پزیشکی",
      feature_4_desc: "دروستکردنی ڕاپۆرتی پیشەیی بە داڕشتنی کەسیکراو و واژووی دیجیتاڵ و پشتڕاستکردنەوەی QR و گەیاندنی یەکسەر لە ڕێگەی واتسئەپ یان ئیمەیل.",

      feature_5_title: "داشبۆردی شیکاری",
      feature_5_desc: "بینینی ڕاستەوخۆ بە داشبۆردی تەواو. شوێنکەوتنی داهات و قەبارەی تاقیکردنەوەکان و کاتی گەیاندن و بڕیاردانی بنەمادراو لەسەر داتا.",

      feature_6_title: "دەسەڵاتی بەپێی ڕۆل",
      feature_6_desc: "سیستەمی دەسەڵاتی ورد بە 50+ دەسەڵات. کۆنتڕۆڵی ئەوەی کێ دەتوانێت چی ببینێت بە تۆماری وردبینی تەواو.",

      equipment_integration: "تەواوبوون بە ئامێرەکانی تاقیگە هەیە",
      equipment_integration_desc: "ئامێرەکانی شیکاری و ئامرازەکانی تاقیگە بە ئاسانی ببەستە بۆ گواستنەوەی ئۆتۆماتیکی ئەنجامەکان و کەمکردنەوەی هەڵەی دەستی.",
      equipment_integration_badge: "نوێ",
      youtube_label: "وانەکان",
      testimonials_rating_label: "· بەپێی ٥٠٠+ هەڵسەنگاندن",
      testimonials_featured: "تایبەت",
      faq_support_subtitle: "تیمەکەمان ئامادەیە یارمەتیت بدات",

      youtube_title: "فێربە چۆن پلاتفۆرمەکە بەکاربهێنیت",
      youtube_desc: "ڤیدیۆ فێرکارییەکانمان سەیربکە بۆ بەکارهێنانی باشترین سیستەمەکە",
      youtube_cta: "سەیرکردنی فێرکارییەکان",

      // How it Works
      how_badge: "پرۆسەیەکی ئاسان",
      how_title: "دەستپێبکە لە",
      how_title_highlight: "3 هەنگاوی ئاسان",
      how_description: "پرۆسەی دەستپێکردنی ئاسانمان تاقیگەکەت بە خێرایی کاردەخات.",

      how_step_1_title: "دروستکردنی هەژمار",
      how_step_1_desc: "لە چەند خولەکدا تۆمار بکە و پڕۆفایلی تاقیگەکەت بە زانیاری بنەڕەتی دابنێ.",

      how_step_2_title: "دانانی تاقیکردنەوە و بەکارهێنەران",
      how_step_2_desc: "لیستی تاقیکردنەوەکانت بهێنەرەوە، نرخی سەرچاوە دابنێ، و ئەندامانی تیمەکەت بانگهێشت بکە.",

      how_step_3_title: "دەستپێکردنی پرۆسێسکردن",
      how_step_3_desc: "دەستبکە بە تۆمارکردنی نەخۆشەکان و پرۆسێسکردنی تاقیکردنەوەکان و گەیاندنی ئەنجامەکان بە یەکسەر.",

      // Testimonials
      testimonials_badge: "چیرۆکی کڕیاران",
      testimonials_title: "خۆشەویستکراو لەلایەن",
      testimonials_title_highlight: "پسپۆڕانی تاقیگە",
      testimonials_description: "ببینە کڕیارەکانمان چی دەڵێن دەربارەی گۆڕینی کارەکانی تاقیگەکانیان.",

      testimonial_1_text: "ئەم سیستەمە شۆڕشی کردووە لە شێوازی بەڕێوەبردنی تاقیگەکەمان. تایبەتمەندییەکانی ئۆتۆماسیۆن بە تەنها کاتژمێرانی بێشومارمان لە هەفتەیەکدا پاشەکەوت کردووە.",
      testimonial_1_name: "د. ئەحمەد ئەلڕاشید",
      testimonial_1_role: "بەڕێوەبەری تاقیگە، تاقیگەی پزیشکی ناوەندی",

      testimonial_2_text: "توانای ڕاپۆرتەکان نایابە. نەخۆشەکانمان حەزیان لە وەرگرتنی ئەنجامەکانیان بە یەکسەر لە ڕێگەی واتسئەپەوە بە پشتڕاستکردنەوەی QR.",
      testimonial_2_name: "د. سارا حەسەن",
      testimonial_2_role: "سەرۆکی پاتۆلۆجیست، سیتی دایەگنۆستیکس",

      testimonial_3_text: "لەکۆتاییدا، سیستەمێک کە پێویستییەکانی تاقیگە مۆدێرنەکان تێدەگات. پشتیوانی کوردی و عەرەبی و ڕووکاری RTL تەواوە بۆ ناوچەکەمان.",
      testimonial_3_name: "د. محەمەد عەلی",
      testimonial_3_role: "خاوەن، تۆڕی تاقیگەکانی پریمیەم",

      // Pricing
      nav_pricing: "نرخەکان",
      pricing_badge: "نرخی ئاسان",
      pricing_title: "پلانی تەواو",
      pricing_title_highlight: "هەڵبژێرە بۆ تاقیگەکەت",
      pricing_description: "نرخی ڕوون بەبێ کرێی شاراوە. بە ئەوەی پێویستتە دەستپێبکە و بە گەشەکردنت فراوان ببە.",

      pricing_currency: "د.ع",

      pricing_plan_1_name: "بنەڕەتی",
      pricing_plan_1_users: "1 بەکارهێنەر",
      pricing_plan_1_monthly: "10,000",
      pricing_plan_1_annual: "100,000",
      pricing_plan_1_f1: "بەڕێوەبردنی نەخۆشەکان",
      pricing_plan_1_f2: "پرۆسێسکردنی تاقیکردنەوەکان",
      pricing_plan_1_f3: "ڕاپۆرتی بنەڕەتی",
      pricing_plan_1_f4: "پشتیوانی بە ئیمەیل",
      pricing_plan_1_cta: "دەستپێبکە",

      pricing_plan_2_name: "ستاندارد",
      pricing_plan_2_users: "2 بەکارهێنەر",
      pricing_plan_2_monthly: "15,000",
      pricing_plan_2_annual: "150,000",
      pricing_plan_2_f1: "هەموو تایبەتمەندییەکانی بنەڕەتی",
      pricing_plan_2_f2: "پسوولەی زیرەک",
      pricing_plan_2_f3: "گەیاندن بە واتسئەپ",
      pricing_plan_2_f4: "پشتیوانی ئەولەویەتدار",
      pricing_plan_2_cta: "دەستپێبکە",

      pricing_plan_3_name: "پیشەیی",
      pricing_plan_3_users: "5 بەکارهێنەر",
      pricing_plan_3_monthly: "35,000",
      pricing_plan_3_annual: "350,000",
      pricing_plan_3_f1: "هەموو تایبەتمەندییەکانی ستاندارد",
      pricing_plan_3_f2: "داشبۆردی شیکاری",
      pricing_plan_3_f3: "داڕشتنی کەسیکراو",
      pricing_plan_3_f4: "دەستگەیشتن بە API",
      pricing_plan_3_cta: "دەستپێبکە",

      pricing_plan_4_name: "بێسنوور",
      pricing_plan_4_users: "بەکارهێنەری بێسنوور",
      pricing_plan_4_monthly: "75,000",
      pricing_plan_4_annual: "750,000",
      pricing_plan_4_f1: "هەموو تایبەتمەندییەکانی پیشەیی",
      pricing_plan_4_f2: "لقی بێسنوور",
      pricing_plan_4_f3: "بەڕێوەبەری هەژماری تایبەت",
      pricing_plan_4_f4: "پشتیوانی تەلەفۆنی 24/7",
      pricing_plan_4_cta: "دەستپێبکە",
      pricing_plan_4_popular: "باوترین",

      pricing_plan_5_name: "دامەزراوە",
      pricing_plan_5_users: "کەسیکراو",
      pricing_plan_5_desc: "پێویستت بە چارەسەرێکی تایبەت بۆ تۆڕی تاقیگەکانتە؟ ئێمە ڕێکخستنی کەسیکراو و ژێرخانی تایبەت و پشتیوانی پایە بەرز پێشکەش دەکەین.",
      pricing_plan_5_branch_note: "+ 75,000 د.ع بۆ هەر لقێکی زیادە",
      pricing_plan_5_f1: "هەموو تایبەتمەندییەکانی بێسنوور",
      pricing_plan_5_f2: "تەواوکردنی کەسیکراو",
      pricing_plan_5_f3: "گەرەنتی SLA",
      pricing_plan_5_f4: "ڕاهێنان لە شوێن",
      pricing_plan_5_cta: "پەیوەندیمان پێوە بکە",

      pricing_per_month: "/مانگ",
      pricing_per_year: "/ساڵ",

      // Dashboard mockup
      weekly_tests: "تاقیکردنەوەکانی هەفتانە",
      recent_results: "ئەنجامە تازەکان",
      completed: "تەواوبوو",
      processing: "لە پرۆسێسکردندایە",
      result_delivered: "ئەنجام گەیەندرا",
      sent_via_whatsapp: "نێردرا بە واتسئەپ",
      this_week: "ئەم هەفتەیە",
      increase: "زیادبوون",
      tap_for_details: "لێبدە بۆ وردەکاری",
      didnt_find_answer: "وەڵامەکەت نەدۆزیتەوە؟",
      contact_support: "پەیوەندی بە پشتیوانی",

      // FAQ
      faq_badge: "پرسیارە باوەکان",
      faq_title: "پرسیارە",
      faq_title_highlight: "دووبارەکان",
      faq_description: "وەڵامی پرسیارە باوەکان دەربارەی سیستەمی بەڕێوەبردنی تاقیگەکەمان بدۆزەرەوە.",

      faq_1_q: "دامەزراندنی سیستەمەکە چەند دەخایەنێت؟",
      faq_1_a: "زۆربەی تاقیگەکان لە ماوەی 24-48 کاتژمێردا بە تەواوی کاردەکەن. تیمەکەمان پشتیوانی تەواو دەدات لە دامەزراندن، بە گەل گواستنەوەی داتا و ڕێکخستنی تاقیکردنەوەکان و ڕاهێنانی ستاف.",

      faq_2_q: "ئایا داتاکانم سەلامەت و پابەندە؟",
      faq_2_a: "بە تەواوی. ئێمە شفرکردنی ئاستی دامەزراوە و پاشکەوتی بەردەوام بەکاردەهێنین و پابەندین بە ستانداردەکانی پاراستنی داتای تەندروستی. داتاکانت بە سەلامەتی هەڵدەگیرێت بە کۆنتڕۆڵی دەستگەیشتن بەپێی ڕۆل.",

      faq_3_q: "ئایا دەتوانم داتای نەخۆش و تاقیکردنەوەکانی ئێستام بهێنمە ژوورەوە؟",
      faq_3_a: "بەڵێ! ئێمە هاوردەکردنی بەکۆمەڵ لە Excel و CSV و زۆربەی سیستەمە کۆنەکان پشتیوانی دەکەین. تیمەکەمان یارمەتیت دەدات لە گواستنەوەی داتا بۆ دڵنیایی لە گواستنەوەیەکی ئاسان.",

      faq_4_q: "ئایا لقی تاقیگەی فرە پشتیوانی دەکەن؟",
      faq_4_a: "بەڵێ، سیستەمەکەمان بۆ کارکردنی فرە لق دیزاین کراوە. دەتوانیت چەند شوێنێک بەڕێوەببەیت بە ڕاپۆرتی ناوەندی و ڕێکخستنی تایبەت بە هەر لقێک.",

      faq_5_q: "چ جۆرە پشتیوانییەک پێشکەش دەکەن؟",
      faq_5_a: "ئێمە پشتیوانی تەکنیکی 24/7 پێشکەش دەکەین لە ڕێگەی چات و ئیمەیل و تەلەفۆن. پلانە تایبەتەکان بەڕێوەبەری هەژماری تایبەت و پشتیوانی ئەولەویەتدار لەخۆدەگرن.",

      faq_6_q: "ئایا دەتوانم ڕاپۆرت و پسوولەکان کەسیکراو بکەم؟",
      faq_6_a: "بەڵێ، کۆنتڕۆڵی تەواوت هەیە لەسەر داڕشتنی ڕاپۆرت و فۆرماتی پسوولە و براند. لۆگۆکەت زیادبکە، داڕشتنەکان کەسیکراو بکە، و داڕشتنی تایبەت بە هەر بەشێک دروست بکە.",

      // CTA
      cta_badge: "ئەمڕۆ دەستپێبکە",
      cta_title: "ئامادەیت بۆ گۆڕینی",
      cta_title_highlight: "تاقیگەکەت؟",
      cta_description: "بچۆ لای سەدان تاقیگە کە کارەکانیان نوێکردۆتەوە. ئەمڕۆ تاقیکردنەوەی بێبەرامبەرت دەستپێبکە - پێویست بە کارتی بانکی نییە.",
      cta_primary: "دەستپێکردنی تاقیکردنەوەی بێبەرامبەر",
      cta_secondary: "ڕێکخستنی دیمۆ",
      cta_feature_1: "تاقیکردنەوەی بێبەرامبەر 14 ڕۆژ",
      cta_feature_2: "پێویست بە کارتی بانکی نییە",
      cta_feature_3: "دەستگەیشتن بە هەموو تایبەتمەندییەکان",
      cta_feature_4: "هەڵوەشاندنەوە لە هەر کاتێک",

      // Footer
      footer_description: "سیستەمی تەواوی بەڕێوەبردنی تاقیگەی پزیشکی دیزاین کراو بۆ دامەزراوە تەندروستییە مۆدێرنەکان. کارەکان ئاسان بکە و نایابی پێشکەش بکە.",
      footer_product: "بەرهەم",
      footer_features: "تایبەتمەندییەکان",
      footer_pricing: "نرخەکان",
      footer_updates: "نوێکارییەکان",
      footer_company: "کۆمپانیا",
      footer_about: "دەربارەی ئێمە",
      footer_careers: "کار",
      footer_contact: "پەیوەندی",
      footer_resources: "سەرچاوەکان",
      footer_docs: "بەڵگەنامە",
      footer_help: "ناوەندی یارمەتی",
      footer_api: "سەرچاوەی API",
      footer_legal: "یاسایی",
      footer_privacy: "سیاسەتی تایبەتمەندی",
      footer_terms: "مەرجەکانی خزمەتگوزاری",
      footer_rights: "هەموو مافەکان پارێزراون.",
      footer_made_with: "دروستکراوە بە",
      footer_for: "بۆ تاقیگەکانی جیهان",
    }
  };
  return translations[lang.value]?.[key] || translations.en[key] || key;
};

const languages = [
  { code: "ar", label: "العربية", short: "AR", flag: "🇮🇶" },
  { code: "en", label: "English", short: "EN", flag: "🇬🇧" },
  { code: "ku", label: "کوردی", short: "KU", flag: "🇮🇶" },
];
const langMenuOpen = ref(false);
const currentLang = computed(() => languages.find((l) => l.code === lang.value) || languages[0]);
const setLanguage = (code) => {
  if (code === lang.value) { langMenuOpen.value = false; return; }
  localStorage.setItem("locale", code);
  window.location.reload();
};
const onClickOutsideLang = (e) => {
  if (!e.target.closest("[data-lang-menu]")) langMenuOpen.value = false;
};
onMounted(() => document.addEventListener("click", onClickOutsideLang));
onUnmounted(() => document.removeEventListener("click", onClickOutsideLang));

const goToLogin = () => {
  router.push("/login");
};

const goToRegister = () => {
  router.push("/register");
};

const scrollTo = (id) => {
  document.getElementById(id)?.scrollIntoView({ behavior: "smooth" });
};

// Mobile menu
const mobileMenuOpen = ref(false);
</script>

<template>
  <div class="min-h-screen bg-white" :dir="lang === 'en' ? 'ltr' : 'rtl'">

    <!-- ===================== NAVIGATION ===================== -->
    <nav
      class="fixed top-0 inset-x-0 z-50 transition-all duration-300"
      :class="scrolled ? 'bg-slate-950/85 backdrop-blur-xl border-b border-white/10 py-1' : 'bg-transparent border-b border-transparent py-2'"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <!-- Logo / Brand -->
          <a @click.prevent="scrollTo('top')" href="#top" class="flex items-center gap-3 group cursor-pointer">
            <div class="relative w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/40 ring-1 ring-white/20 overflow-hidden">
              <img src="/lad_logo.png" alt="Logo" class="w-7 h-7 object-contain relative z-10" />
              <div class="absolute inset-0 bg-gradient-to-tr from-transparent to-white/20"></div>
            </div>
            <div class="leading-tight">
              <div class="flex items-center gap-1.5">
                <span class="text-base sm:text-lg font-bold text-white tracking-tight">{{ t('nav_brand') }}</span>
              </div>
              <p class="text-[10px] text-slate-400 font-medium">{{ t('nav_tagline') }}</p>
            </div>
          </a>

          <!-- Desktop Navigation: pill background -->
          <div class="hidden lg:flex items-center gap-0.5 bg-white/5 border border-white/10 rounded-full px-1.5 py-1 backdrop-blur">
            <a @click.prevent="scrollTo('features')" href="#features" class="px-4 py-1.5 text-slate-300 hover:text-white hover:bg-white/10 rounded-full text-sm font-medium transition-all cursor-pointer">{{ t('nav_features') }}</a>
            <a @click.prevent="scrollTo('how-it-works')" href="#how-it-works" class="px-4 py-1.5 text-slate-300 hover:text-white hover:bg-white/10 rounded-full text-sm font-medium transition-all cursor-pointer">{{ t('nav_how_it_works') }}</a>
            <a @click.prevent="scrollTo('testimonials')" href="#testimonials" class="px-4 py-1.5 text-slate-300 hover:text-white hover:bg-white/10 rounded-full text-sm font-medium transition-all cursor-pointer">{{ t('nav_testimonials') }}</a>
            <a @click.prevent="scrollTo('pricing')" href="#pricing" class="px-4 py-1.5 text-slate-300 hover:text-white hover:bg-white/10 rounded-full text-sm font-medium transition-all cursor-pointer">{{ t('nav_pricing') }}</a>
            <a @click.prevent="scrollTo('faq')" href="#faq" class="px-4 py-1.5 text-slate-300 hover:text-white hover:bg-white/10 rounded-full text-sm font-medium transition-all cursor-pointer">{{ t('nav_faq') }}</a>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2">
            <!-- Language Dropdown -->
            <div class="relative hidden sm:block" data-lang-menu>
              <button
                @click.stop="langMenuOpen = !langMenuOpen"
                class="flex items-center gap-1.5 h-9 px-3 text-slate-300 hover:text-white hover:bg-white/10 rounded-full transition-colors text-xs font-bold border border-white/10"
                :title="currentLang.label"
              >
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                </svg>
                {{ currentLang.short }}
                <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': langMenuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
              >
                <div
                  v-if="langMenuOpen"
                  class="absolute end-0 mt-2 w-44 bg-slate-900/95 backdrop-blur-xl border border-white/10 rounded-2xl shadow-2xl shadow-black/50 overflow-hidden py-1.5"
                >
                  <button
                    v-for="l in languages"
                    :key="l.code"
                    @click.stop="setLanguage(l.code)"
                    class="w-full px-4 py-2.5 flex items-center gap-3 text-sm transition-colors"
                    :class="l.code === lang ? 'bg-primary-500/15 text-primary-300' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
                  >
                    <span class="text-base">{{ l.flag }}</span>
                    <span class="flex-1 text-start font-medium">{{ l.label }}</span>
                    <svg v-if="l.code === lang" class="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                  </button>
                </div>
              </Transition>
            </div>

            <button
              @click="goToLogin"
              class="hidden md:flex items-center gap-1.5 px-3 lg:px-4 py-2 text-slate-300 hover:text-white font-medium text-sm transition-colors"
            >
              {{ t('nav_login') }}
              <svg class="w-3.5 h-3.5 rtl:rotate-180 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </button>

            <button
              @click="goToRegister"
              class="hidden md:flex relative px-4 lg:px-5 py-2 lg:py-2.5 bg-primary-500 hover:bg-primary-400 text-white font-bold rounded-full text-xs lg:text-sm shadow-lg shadow-primary-500/40 hover:shadow-2xl hover:shadow-primary-500/60 transition-all overflow-hidden group"
            >
              <span class="relative z-10 flex items-center gap-1.5">
                {{ t('nav_register') }}
                <svg class="w-3.5 h-3.5 rtl:rotate-180 transition-transform group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
              </span>
            </button>

            <!-- Mobile Menu Button -->
            <button
              @click="mobileMenuOpen = !mobileMenuOpen"
              class="lg:hidden w-10 h-10 flex items-center justify-center text-white hover:bg-white/10 rounded-xl border border-white/10 transition-colors"
            >
              <svg v-if="!mobileMenuOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
              <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Mobile Menu -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 -translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-2"
        >
          <div v-if="mobileMenuOpen" class="lg:hidden pb-5 border-t border-white/10 mt-2 pt-4 bg-slate-950/95 backdrop-blur-xl rounded-b-2xl px-2">
            <div class="flex flex-col gap-1">
              <a @click.prevent="scrollTo('features'); mobileMenuOpen = false" href="#features" class="px-4 py-3 text-slate-300 hover:text-white hover:bg-white/5 rounded-xl font-medium transition-colors">{{ t('nav_features') }}</a>
              <a @click.prevent="scrollTo('how-it-works'); mobileMenuOpen = false" href="#how-it-works" class="px-4 py-3 text-slate-300 hover:text-white hover:bg-white/5 rounded-xl font-medium transition-colors">{{ t('nav_how_it_works') }}</a>
              <a @click.prevent="scrollTo('testimonials'); mobileMenuOpen = false" href="#testimonials" class="px-4 py-3 text-slate-300 hover:text-white hover:bg-white/5 rounded-xl font-medium transition-colors">{{ t('nav_testimonials') }}</a>
              <a @click.prevent="scrollTo('pricing'); mobileMenuOpen = false" href="#pricing" class="px-4 py-3 text-slate-300 hover:text-white hover:bg-white/5 rounded-xl font-medium transition-colors">{{ t('nav_pricing') }}</a>
              <a @click.prevent="scrollTo('faq'); mobileMenuOpen = false" href="#faq" class="px-4 py-3 text-slate-300 hover:text-white hover:bg-white/5 rounded-xl font-medium transition-colors">{{ t('nav_faq') }}</a>

              <!-- Mobile Language Picker -->
              <div class="mt-3 pt-3 border-t border-white/10">
                <div class="px-4 mb-2 text-[10px] uppercase tracking-wider text-slate-500 font-semibold">{{ t('language') || 'Language' }}</div>
                <div class="grid grid-cols-3 gap-1.5 px-1">
                  <button
                    v-for="l in languages"
                    :key="'m-' + l.code"
                    @click="setLanguage(l.code)"
                    class="flex flex-col items-center justify-center gap-1 py-2.5 rounded-xl border transition-all"
                    :class="l.code === lang ? 'bg-primary-500/15 border-primary-500/40 text-primary-300' : 'bg-white/[0.02] border-white/10 text-slate-300 hover:bg-white/5'"
                  >
                    <span class="text-base">{{ l.flag }}</span>
                    <span class="text-[10px] font-bold">{{ l.short }}</span>
                  </button>
                </div>
              </div>

              <div class="flex gap-2 mt-3 pt-3 border-t border-white/10 px-1">
                <button @click="goToLogin" class="flex-1 px-4 py-3 border border-white/20 text-slate-200 font-semibold rounded-xl hover:bg-white/5 transition-colors">{{ t('nav_login') }}</button>
                <button @click="goToRegister" class="flex-1 px-4 py-3 bg-primary-500 hover:bg-primary-400 text-white font-bold rounded-xl shadow-lg shadow-primary-500/40">{{ t('nav_register') }}</button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </nav>

    <!-- ===================== HERO SECTION ===================== -->
    <section id="top" class="relative pt-24 pb-14 sm:pt-28 sm:pb-16 lg:pt-40 lg:pb-32 overflow-hidden bg-slate-950">
      <!-- Lab grid pattern background -->
      <div class="absolute inset-0 opacity-[0.07]" style="background-image: linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px); background-size: 40px 40px;"></div>
      <!-- Radial vignette (uses primary brand color) -->
      <div class="absolute inset-0" style="background: radial-gradient(ellipse at top, color-mix(in srgb, var(--color-primary-500) 18%, transparent), transparent 55%);"></div>
      <div class="absolute inset-0" style="background: radial-gradient(ellipse at bottom right, color-mix(in srgb, var(--color-primary-600) 15%, transparent), transparent 60%);"></div>
      <!-- Soft top fade -->
      <div class="absolute top-0 inset-x-0 h-32 bg-gradient-to-b from-slate-950 to-transparent"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-12 gap-10 lg:gap-12 items-center">
          <!-- Left: Text Content -->
          <div class="lg:col-span-7 text-center lg:text-start">
            <!-- Live status badge -->
            <div class="inline-flex items-center gap-2 sm:gap-2.5 px-2.5 sm:px-3 py-1 sm:py-1.5 bg-white/[0.04] border border-white/10 rounded-full mb-5 sm:mb-7 backdrop-blur-sm">
              <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-500 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-success-500"></span>
              </span>
              <span class="text-[10px] sm:text-xs font-semibold text-slate-300 tracking-wide">{{ t('hero_badge') }}</span>
              <span class="w-px h-3 bg-white/15 hidden sm:block"></span>
              <span class="text-[10px] sm:text-xs font-bold text-success-500 hidden sm:inline">{{ t('hero_live') }}</span>
            </div>

            <!-- Title — bigger, tighter, with accent strip -->
            <h1 class="text-[32px] sm:text-5xl lg:text-6xl xl:text-7xl font-bold leading-[1.1] sm:leading-[1.05] tracking-tight mb-5 sm:mb-6 text-white">
              {{ t('hero_title_1') }}
              <span class="relative inline-block">
                <span class="relative z-10 bg-gradient-to-r from-primary-300 via-primary-200 to-primary-400 bg-clip-text text-transparent">{{ t('hero_title_2') }}</span>
                <svg class="absolute -bottom-2 left-0 w-full" height="14" viewBox="0 0 200 14" fill="none" preserveAspectRatio="none">
                  <path d="M2 8 Q 50 2, 100 7 T 198 6" stroke="url(#u1)" stroke-width="3" stroke-linecap="round" fill="none" />
                  <defs>
                    <linearGradient id="u1" x1="0" y1="0" x2="200" y2="0">
                      <stop offset="0%" stop-color="var(--color-primary-400)" />
                      <stop offset="100%" stop-color="var(--color-primary-500)" />
                    </linearGradient>
                  </defs>
                </svg>
              </span>
              <br>
              {{ t('hero_title_3') }}
            </h1>

            <!-- Description -->
            <p class="text-sm sm:text-base lg:text-lg text-slate-400 mb-7 sm:mb-9 max-w-xl mx-auto lg:mx-0 leading-relaxed px-2 sm:px-0">
              {{ t('hero_description') }}
            </p>

            <!-- CTA Buttons -->
            <div class="flex flex-row items-center gap-2 sm:gap-3 justify-center lg:justify-start mb-7 sm:mb-10">
              <button
                @click="goToRegister"
                class="group relative px-4 sm:px-7 py-2.5 sm:py-4 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white font-bold rounded-xl sm:rounded-2xl transition-all shadow-lg sm:shadow-2xl shadow-primary-500/40 hover:shadow-primary-500/60 hover:-translate-y-0.5 flex items-center justify-center gap-1.5 sm:gap-2.5 text-xs sm:text-base"
              >
                <span class="relative">{{ t('hero_cta_primary') }}</span>
                <svg class="relative w-3.5 h-3.5 sm:w-5 sm:h-5 rtl:rotate-180 transition-transform group-hover:translate-x-1 rtl:group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
              </button>
              <button
                @click="goToLogin"
                class="group px-4 sm:px-7 py-2.5 sm:py-4 bg-transparent hover:bg-white/5 text-white font-semibold rounded-xl sm:rounded-2xl transition-all border border-white/15 hover:border-white/30 flex items-center justify-center gap-1.5 sm:gap-2.5 text-xs sm:text-base"
              >
                <span class="w-5 h-5 sm:w-7 sm:h-7 rounded-full bg-white/10 group-hover:bg-primary-500/30 flex items-center justify-center transition-colors shrink-0">
                  <svg class="w-2.5 h-2.5 sm:w-3.5 sm:h-3.5 text-primary-300" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z" />
                  </svg>
                </span>
                {{ t('hero_cta_secondary') }}
              </button>
            </div>

            <!-- Trust strip: lab logos / certifications -->
            <div class="flex flex-wrap items-center gap-x-4 sm:gap-x-6 gap-y-2 sm:gap-y-3 justify-center lg:justify-start text-[11px] sm:text-xs text-slate-500">
              <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-success-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm-1.06 14L7 12.06l1.41-1.41 2.53 2.53 5.06-5.06L17.41 9 10.94 16z"/></svg>
                {{ t('hero_iso') }}
              </div>
              <div class="w-px h-4 bg-white/10 hidden sm:block"></div>
              <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-success-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm-1.06 14L7 12.06l1.41-1.41 2.53 2.53 5.06-5.06L17.41 9 10.94 16z"/></svg>
                {{ t('hero_hipaa') }}
              </div>
              <div class="w-px h-4 bg-white/10 hidden sm:block"></div>
              <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-success-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm-1.06 14L7 12.06l1.41-1.41 2.53 2.53 5.06-5.06L17.41 9 10.94 16z"/></svg>
                {{ t('hero_uptime') }}
              </div>
            </div>
          </div>

          <!-- Right: Live ticker dashboard mockup -->
          <div class="lg:col-span-5 hidden lg:block relative">
            <!-- Glow under card -->
            <div class="absolute -inset-4 bg-gradient-to-tr from-primary-500/30 via-primary-400/20 to-transparent blur-3xl rounded-full"></div>

            <!-- Tilted card stack -->
            <div class="relative" style="transform: perspective(1200px) rotateY(-8deg) rotateX(4deg);">
              <!-- Background card -->
              <div class="absolute -top-3 -end-3 w-full h-full bg-gradient-to-br from-primary-600/30 to-primary-700/20 rounded-3xl border border-white/5"></div>

              <!-- Main card -->
              <div class="relative bg-slate-900/90 rounded-3xl border border-white/10 shadow-2xl shadow-black/60 backdrop-blur-xl overflow-hidden">
                <!-- Top bar -->
                <div class="px-5 py-3 border-b border-white/5 bg-slate-950/40 flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-md shadow-primary-500/40">
                      <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                    </div>
                    <div>
                      <div class="text-[11px] text-white font-bold">{{ t('hero_feed_title') }}</div>
                      <div class="text-[9px] text-slate-500">{{ t('hero_feed_url') }}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="relative flex h-2 w-2">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-500 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-2 w-2 bg-success-500"></span>
                    </span>
                    <span class="text-[10px] font-bold text-success-500">{{ t('hero_live') }}</span>
                  </div>
                </div>

                <!-- KPI row -->
                <div class="grid grid-cols-3 gap-2 p-4 border-b border-white/5">
                  <div class="bg-white/[0.03] border border-white/5 rounded-xl p-3">
                    <div class="text-[9px] uppercase tracking-wider text-slate-500 font-semibold">{{ t('hero_kpi_tests_today') }}</div>
                    <div class="text-xl font-bold text-white mt-0.5">1,284</div>
                    <div class="text-[10px] text-success-500 font-bold">↑ 18.2%</div>
                  </div>
                  <div class="bg-white/[0.03] border border-white/5 rounded-xl p-3">
                    <div class="text-[9px] uppercase tracking-wider text-slate-500 font-semibold">{{ t('hero_kpi_pending') }}</div>
                    <div class="text-xl font-bold text-white mt-0.5">37</div>
                    <div class="text-[10px] text-warning-500 font-bold">{{ t('hero_kpi_in_queue') }}</div>
                  </div>
                  <div class="bg-white/[0.03] border border-white/5 rounded-xl p-3">
                    <div class="text-[9px] uppercase tracking-wider text-slate-500 font-semibold">{{ t('hero_kpi_accuracy') }}</div>
                    <div class="text-xl font-bold bg-gradient-to-r from-primary-300 to-primary-400 bg-clip-text text-transparent mt-0.5">99.9%</div>
                    <div class="text-[10px] text-success-500 font-bold">{{ t('hero_kpi_excellent') }}</div>
                  </div>
                </div>

                <!-- Live ticker -->
                <div class="p-4">
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">{{ t('hero_recent_results') }}</span>
                    <span class="text-[10px] text-slate-600">{{ t('hero_auto_refresh') }}</span>
                  </div>
                  <div class="space-y-1.5">
                    <Transition name="ticker" mode="out-in">
                      <div :key="tickerIndex" class="bg-gradient-to-r from-primary-500/10 to-transparent border border-primary-500/20 rounded-xl px-3 py-2.5 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                          <div class="w-8 h-8 rounded-lg bg-primary-500/20 border border-primary-500/30 flex items-center justify-center">
                            <span class="text-[9px] font-mono font-bold text-primary-300">{{ tickerItems[tickerIndex].patient }}</span>
                          </div>
                          <div>
                            <div class="text-xs text-white font-bold">{{ tickerItems[tickerIndex].code }}</div>
                            <div class="text-[9px] text-slate-500 font-mono">{{ t('hero_just_now') }}</div>
                          </div>
                        </div>
                        <span
                          class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
                          :class="{
                            'bg-success-500/20 text-success-200 border border-success-500/30': tickerItems[tickerIndex].color === 'success',
                            'bg-warning-500/20 text-warning-200 border border-warning-500/30': tickerItems[tickerIndex].color === 'warning',
                            'bg-danger-500/20 text-danger-200 border border-danger-500/30': tickerItems[tickerIndex].color === 'danger',
                          }"
                        >{{ tickerItems[tickerIndex].status }}</span>
                      </div>
                    </Transition>
                    <!-- Static rows -->
                    <div v-for="i in [1,2,3]" :key="'s-' + i" class="bg-white/[0.02] border border-white/5 rounded-xl px-3 py-2 flex items-center justify-between opacity-60">
                      <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-white/5 flex items-center justify-center">
                          <span class="text-[9px] font-mono font-bold text-slate-400">{{ tickerItems[(tickerIndex + i) % tickerItems.length].patient }}</span>
                        </div>
                        <div class="text-[11px] text-slate-300 font-medium">{{ tickerItems[(tickerIndex + i) % tickerItems.length].code }}</div>
                      </div>
                      <span class="text-[9px] text-slate-500">{{ t('hero_minutes_ago').replace('{n}', i) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Bottom mini chart -->
                <div class="px-4 pb-4">
                  <div class="bg-white/[0.03] border border-white/5 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-2">
                      <span class="text-[10px] text-slate-400 font-semibold">{{ t('hero_chart_title') }}</span>
                      <span class="text-[10px] text-success-500 font-bold">↑ 24%</span>
                    </div>
                    <div class="flex items-end gap-1 h-12">
                      <div v-for="(h, i) in [40, 55, 35, 70, 50, 80, 100]" :key="'b-' + i" :style="{ height: h + '%' }" class="flex-1 rounded-t-sm transition-all" :class="i === 6 ? 'bg-gradient-to-t from-primary-500 to-primary-300' : 'bg-primary-500/30'"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Floating badge: top -->
            <div class="absolute -top-4 -end-4 z-20 animate-float-delayed">
              <div class="bg-white rounded-2xl shadow-2xl shadow-black/40 px-3 py-2.5 flex items-center gap-2.5 border border-white/40">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-success-400 to-success-600 flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </div>
                <div>
                  <div class="text-[11px] font-bold text-slate-900 leading-tight">{{ t('hero_result_delivered') }}</div>
                  <div class="text-[9px] text-slate-500">{{ t('hero_via_whatsapp') }}</div>
                </div>
              </div>
            </div>

            <!-- Floating badge: bottom -->
            <div class="absolute -bottom-4 -start-6 z-20 animate-float">
              <div class="bg-slate-900 rounded-2xl shadow-2xl shadow-primary-500/30 px-3 py-2.5 flex items-center gap-2.5 border border-primary-500/30">
                <div class="w-9 h-9 rounded-xl bg-primary-500/20 border border-primary-500/40 flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5 text-primary-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" /></svg>
                </div>
                <div>
                  <div class="text-[11px] font-bold text-white leading-tight">{{ t('hero_labs_online') }}</div>
                  <div class="text-[9px] text-slate-400">{{ t('hero_across_region') }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right: Dashboard Mockup REMOVED -->
          <div v-if="false" class="relative hidden lg:block">
            <!-- Browser Window -->
            <div class="relative bg-slate-800/80 rounded-2xl border border-white/10 shadow-2xl shadow-black/40 backdrop-blur-sm overflow-hidden">
              <!-- Browser Chrome -->
              <div class="flex items-center gap-2 px-4 py-3 border-b border-white/10 bg-slate-900/50">
                <div class="flex gap-1.5">
                  <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                  <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                  <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
                </div>
                <div class="flex-1 mx-4">
                  <div class="bg-slate-700/60 rounded-lg px-3 py-1.5 text-xs text-gray-400 text-center">digitallab.app/dashboard</div>
                </div>
              </div>
              <!-- Dashboard Content -->
              <div class="p-4 sm:p-5 space-y-4">
                <!-- Stats Grid -->
                <div class="grid grid-cols-3 gap-3">
                  <div class="bg-slate-700/40 rounded-xl p-3 border border-white/5">
                    <p class="text-[10px] text-gray-500 mb-1">{{ t('stat_labs') }}</p>
                    <p class="text-lg font-bold text-white">500+</p>
                    <div class="flex items-center gap-1 mt-1">
                      <svg class="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                      <span class="text-[10px] text-emerald-400">+12%</span>
                    </div>
                  </div>
                  <div class="bg-slate-700/40 rounded-xl p-3 border border-white/5">
                    <p class="text-[10px] text-gray-500 mb-1">{{ t('stat_tests') }}</p>
                    <p class="text-lg font-bold text-white">2M+</p>
                    <div class="flex items-center gap-1 mt-1">
                      <svg class="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                      <span class="text-[10px] text-emerald-400">+24%</span>
                    </div>
                  </div>
                  <div class="bg-slate-700/40 rounded-xl p-3 border border-white/5">
                    <p class="text-[10px] text-gray-500 mb-1">{{ t('stat_accuracy') }}</p>
                    <p class="text-lg font-bold text-primary-400">99.9%</p>
                    <div class="flex items-center gap-1 mt-1">
                      <svg class="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                      <span class="text-[10px] text-emerald-400">+0.3%</span>
                    </div>
                  </div>
                </div>
                <!-- Mini Chart Bar -->
                <div class="bg-slate-700/40 rounded-xl p-3 border border-white/5">
                  <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-gray-400 font-medium">{{ t('weekly_tests') }}</span>
                    <span class="text-xs text-emerald-400 font-medium">+18%</span>
                  </div>
                  <div class="flex items-end gap-1.5 h-16">
                    <div class="flex-1 bg-primary-600/40 rounded-t-sm" style="height: 45%"></div>
                    <div class="flex-1 bg-primary-600/40 rounded-t-sm" style="height: 60%"></div>
                    <div class="flex-1 bg-primary-600/40 rounded-t-sm" style="height: 35%"></div>
                    <div class="flex-1 bg-primary-600/40 rounded-t-sm" style="height: 75%"></div>
                    <div class="flex-1 bg-primary-600/40 rounded-t-sm" style="height: 55%"></div>
                    <div class="flex-1 bg-primary-600/40 rounded-t-sm" style="height: 85%"></div>
                    <div class="flex-1 bg-primary-500 rounded-t-sm" style="height: 100%"></div>
                  </div>
                </div>
                <!-- Mini Table -->
                <div class="bg-slate-700/40 rounded-xl border border-white/5 overflow-hidden">
                  <div class="px-3 py-2 border-b border-white/5">
                    <span class="text-xs text-gray-400 font-medium">{{ t('recent_results') }}</span>
                  </div>
                  <div class="divide-y divide-white/5">
                    <div class="px-3 py-2 flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-full bg-primary-600/30 flex items-center justify-center"><span class="text-[9px] text-primary-300 font-bold">AH</span></div>
                        <span class="text-xs text-gray-300">CBC Panel</span>
                      </div>
                      <span class="text-[10px] px-2 py-0.5 bg-emerald-500/20 text-emerald-400 rounded-full font-medium">{{ t('completed') }}</span>
                    </div>
                    <div class="px-3 py-2 flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-full bg-amber-600/30 flex items-center justify-center"><span class="text-[9px] text-amber-300 font-bold">SM</span></div>
                        <span class="text-xs text-gray-300">Lipid Profile</span>
                      </div>
                      <span class="text-[10px] px-2 py-0.5 bg-amber-500/20 text-amber-400 rounded-full font-medium">{{ t('processing') }}</span>
                    </div>
                    <div class="px-3 py-2 flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-full bg-blue-600/30 flex items-center justify-center"><span class="text-[9px] text-blue-300 font-bold">KR</span></div>
                        <span class="text-xs text-gray-300">Thyroid Panel</span>
                      </div>
                      <span class="text-[10px] px-2 py-0.5 bg-emerald-500/20 text-emerald-400 rounded-full font-medium">{{ t('completed') }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Floating Notification Card -->
            <div class="absolute -bottom-4 -start-8 animate-float">
              <div class="bg-white rounded-xl shadow-xl shadow-black/20 p-3 flex items-center gap-3 border border-gray-100">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div>
                  <p class="text-xs font-semibold text-gray-900">{{ t('result_delivered') }}</p>
                  <p class="text-[10px] text-gray-500">{{ t('sent_via_whatsapp') }}</p>
                </div>
              </div>
            </div>

            <!-- Floating Stats Card -->
            <div class="absolute -top-2 -end-6 animate-float-delayed">
              <div class="bg-white rounded-xl shadow-xl shadow-black/20 p-3 border border-gray-100">
                <div class="flex items-center gap-2 mb-1">
                  <div class="w-7 h-7 rounded-lg bg-primary-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                  </div>
                  <span class="text-[10px] text-gray-500">{{ t('this_week') }}</span>
                </div>
                <p class="text-lg font-bold text-gray-900">1,284</p>
                <p class="text-[10px] text-emerald-600 font-medium">+18.2% {{ t('increase') }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats Row — divider style -->
        <div class="mt-10 sm:mt-16 lg:mt-20 pt-6 sm:pt-10 border-t border-white/10 grid grid-cols-4 gap-2 sm:gap-4 text-center sm:text-start">
          <div class="sm:border-s sm:border-white/10 sm:ps-5 first:border-s-0 first:ps-0">
            <p class="text-base sm:text-4xl font-bold text-white tracking-tight">500<span class="text-primary-400">+</span></p>
            <p class="text-[9px] sm:text-xs text-slate-500 mt-0.5 sm:mt-1 font-semibold uppercase tracking-wider">{{ t('stat_labs') }}</p>
          </div>
          <div class="sm:border-s sm:border-white/10 sm:ps-5">
            <p class="text-base sm:text-4xl font-bold text-white tracking-tight">2M<span class="text-primary-400">+</span></p>
            <p class="text-[9px] sm:text-xs text-slate-500 mt-0.5 sm:mt-1 font-semibold uppercase tracking-wider">{{ t('stat_tests') }}</p>
          </div>
          <div class="sm:border-s sm:border-white/10 sm:ps-5">
            <p class="text-base sm:text-4xl font-bold text-white tracking-tight">1M<span class="text-primary-400">+</span></p>
            <p class="text-[9px] sm:text-xs text-slate-500 mt-0.5 sm:mt-1 font-semibold uppercase tracking-wider">{{ t('stat_patients') }}</p>
          </div>
          <div class="sm:border-s sm:border-white/10 sm:ps-5">
            <p class="text-base sm:text-4xl font-bold bg-gradient-to-r from-primary-300 to-primary-400 bg-clip-text text-transparent tracking-tight">99.9%</p>
            <p class="text-[9px] sm:text-xs text-slate-500 mt-0.5 sm:mt-1 font-semibold uppercase tracking-wider">{{ t('stat_accuracy') }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================== FEATURES SECTION ===================== -->
    <section id="features" class="py-16 sm:py-20 lg:py-28 bg-slate-50 relative overflow-hidden">
      <!-- Subtle brand background decoration -->
      <div class="absolute top-0 start-1/4 w-72 sm:w-96 h-72 sm:h-96 bg-primary-100/50 rounded-full blur-3xl"></div>
      <div class="absolute bottom-0 end-1/4 w-64 sm:w-80 h-64 sm:h-80 bg-primary-50/60 rounded-full blur-3xl"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center max-w-2xl mx-auto mb-10 sm:mb-14 lg:mb-16">
          <div class="inline-flex items-center gap-2 px-3 sm:px-4 py-1 sm:py-1.5 bg-primary-50 border border-primary-200 rounded-full mb-4 sm:mb-6">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            <span class="text-[11px] sm:text-sm font-semibold text-primary-700 uppercase tracking-wide">{{ t('features_badge') }}</span>
          </div>
          <h2 class="text-2xl sm:text-3xl lg:text-5xl font-bold text-slate-900 mb-3 sm:mb-4 leading-tight tracking-tight">
            {{ t('features_title') }}
            <span class="bg-gradient-to-r from-primary-600 to-primary-400 bg-clip-text text-transparent">{{ t('features_title_highlight') }}</span>
          </h2>
          <p class="text-sm sm:text-base lg:text-lg text-slate-500">{{ t('features_description') }}</p>
        </div>

        <!-- Bento Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 lg:grid-rows-3 gap-3 sm:gap-4 lg:gap-5">

          <!-- F1 — Big featured card (sm: full-width, lg: 2x2) -->
          <div
            @click="toggleFeature(1)"
            class="group relative sm:col-span-2 lg:col-span-2 lg:row-span-2 bg-gradient-to-br from-slate-900 via-slate-900 to-primary-950 rounded-2xl sm:rounded-3xl p-5 sm:p-7 lg:p-10 border border-slate-800 overflow-hidden hover:border-primary-500/40 transition-all duration-500 cursor-pointer sm:cursor-default lg:min-h-[420px]"
            :class="{ 'ring-2 ring-primary-500/50': expandedFeature === 1 }"
          >
            <!-- Decorative blob -->
            <div class="absolute -top-20 -end-20 w-72 h-72 bg-primary-500/10 rounded-full blur-3xl group-hover:bg-primary-500/20 transition-all duration-700"></div>
            <!-- Grid pattern -->
            <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 32px 32px;"></div>

            <div class="relative">
              <div class="inline-flex items-center gap-1.5 sm:gap-2 px-2 sm:px-2.5 py-0.5 sm:py-1 bg-primary-500/15 border border-primary-500/30 rounded-full mb-3 sm:mb-5">
                <span class="w-1.5 h-1.5 rounded-full bg-primary-400 animate-pulse"></span>
                <span class="text-[9px] sm:text-xs font-bold uppercase tracking-wider text-primary-300">{{ t('features_badge') }}</span>
              </div>
              <div class="w-12 h-12 sm:w-14 sm:h-14 lg:w-16 lg:h-16 rounded-xl sm:rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center mb-3 sm:mb-5 lg:mb-6 shadow-xl sm:shadow-2xl shadow-primary-500/30">
                <svg class="w-6 h-6 sm:w-7 sm:h-7 lg:w-8 lg:h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
              </div>
              <h3 class="text-lg sm:text-2xl lg:text-3xl font-bold text-white mb-1.5 sm:mb-3 leading-tight">{{ t('feature_1_title') }}</h3>
              <p class="text-xs sm:text-sm lg:text-base text-slate-400 leading-relaxed max-w-md">{{ t('feature_1_desc') }}</p>

              <!-- Mini stats -->
              <div class="flex items-center gap-3 sm:gap-5 lg:gap-6 mt-4 sm:mt-6 lg:mt-8 pt-3 sm:pt-5 lg:pt-6 border-t border-white/10">
                <div>
                  <div class="text-base sm:text-xl lg:text-2xl font-bold text-white">1M+</div>
                  <div class="text-[9px] sm:text-[10px] lg:text-xs text-slate-500 uppercase tracking-wider font-semibold">{{ t('stat_patients') }}</div>
                </div>
                <div class="w-px h-7 sm:h-9 lg:h-10 bg-white/10"></div>
                <div>
                  <div class="text-base sm:text-xl lg:text-2xl font-bold text-primary-400">{{ '< 2s' }}</div>
                  <div class="text-[9px] sm:text-[10px] lg:text-xs text-slate-500 uppercase tracking-wider font-semibold">{{ t('feature_response_time') }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- F2 — Light card -->
          <div
            @click="toggleFeature(2)"
            class="group relative bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 hover:border-primary-300 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer sm:cursor-default overflow-hidden"
            :class="{ 'border-primary-300 shadow-lg': expandedFeature === 2 }"
          >
            <div class="absolute -top-8 -end-8 w-32 h-32 bg-primary-500/5 rounded-full blur-2xl group-hover:bg-primary-500/15 transition-all"></div>
            <div class="relative">
              <div class="w-11 h-11 rounded-xl bg-primary-50 border border-primary-100 flex items-center justify-center mb-4">
                <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
                </svg>
              </div>
              <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-1.5">{{ t('feature_2_title') }}</h3>
              <p class="text-xs sm:text-sm text-slate-500 leading-relaxed line-clamp-3" :class="expandedFeature === 2 ? 'line-clamp-none' : ''">{{ t('feature_2_desc') }}</p>
            </div>
          </div>

          <!-- F3 — Light card -->
          <div
            @click="toggleFeature(3)"
            class="group relative bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 hover:border-primary-300 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer sm:cursor-default overflow-hidden"
            :class="{ 'border-primary-300 shadow-lg': expandedFeature === 3 }"
          >
            <div class="absolute -top-8 -end-8 w-32 h-32 bg-success-500/5 rounded-full blur-2xl group-hover:bg-success-500/15 transition-all"></div>
            <div class="relative">
              <div class="w-11 h-11 rounded-xl bg-success-50 border border-success-100 flex items-center justify-center mb-4">
                <svg class="w-5 h-5 text-success-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                </svg>
              </div>
              <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-1.5">{{ t('feature_3_title') }}</h3>
              <p class="text-xs sm:text-sm text-slate-500 leading-relaxed line-clamp-3" :class="expandedFeature === 3 ? 'line-clamp-none' : ''">{{ t('feature_3_desc') }}</p>
            </div>
          </div>

          <!-- F4 — Light card -->
          <div
            @click="toggleFeature(4)"
            class="group relative bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 hover:border-primary-300 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer sm:cursor-default overflow-hidden"
            :class="{ 'border-primary-300 shadow-lg': expandedFeature === 4 }"
          >
            <div class="absolute -top-8 -end-8 w-32 h-32 bg-info-500/5 rounded-full blur-2xl group-hover:bg-info-500/15 transition-all"></div>
            <div class="relative">
              <div class="w-11 h-11 rounded-xl bg-info-50 border border-info-100 flex items-center justify-center mb-4">
                <svg class="w-5 h-5 text-info-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
              </div>
              <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-1.5">{{ t('feature_4_title') }}</h3>
              <p class="text-xs sm:text-sm text-slate-500 leading-relaxed line-clamp-3" :class="expandedFeature === 4 ? 'line-clamp-none' : ''">{{ t('feature_4_desc') }}</p>
            </div>
          </div>

          <!-- F5 — Light card -->
          <div
            @click="toggleFeature(5)"
            class="group relative bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 hover:border-primary-300 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer sm:cursor-default overflow-hidden"
            :class="{ 'border-primary-300 shadow-lg': expandedFeature === 5 }"
          >
            <div class="absolute -top-8 -end-8 w-32 h-32 bg-warning-500/5 rounded-full blur-2xl group-hover:bg-warning-500/15 transition-all"></div>
            <div class="relative">
              <div class="w-11 h-11 rounded-xl bg-warning-50 border border-warning-100 flex items-center justify-center mb-4">
                <svg class="w-5 h-5 text-warning-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
              </div>
              <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-1.5">{{ t('feature_5_title') }}</h3>
              <p class="text-xs sm:text-sm text-slate-500 leading-relaxed line-clamp-3" :class="expandedFeature === 5 ? 'line-clamp-none' : ''">{{ t('feature_5_desc') }}</p>
            </div>
          </div>

          <!-- F6 — Light card -->
          <div
            @click="toggleFeature(6)"
            class="group relative bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 hover:border-primary-300 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer sm:cursor-default overflow-hidden"
            :class="{ 'border-primary-300 shadow-lg': expandedFeature === 6 }"
          >
            <div class="absolute -top-8 -end-8 w-32 h-32 bg-danger-500/5 rounded-full blur-2xl group-hover:bg-danger-500/15 transition-all"></div>
            <div class="relative">
              <div class="w-11 h-11 rounded-xl bg-danger-50 border border-danger-100 flex items-center justify-center mb-4">
                <svg class="w-5 h-5 text-danger-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
              </div>
              <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-1.5">{{ t('feature_6_title') }}</h3>
              <p class="text-xs sm:text-sm text-slate-500 leading-relaxed line-clamp-3" :class="expandedFeature === 6 ? 'line-clamp-none' : ''">{{ t('feature_6_desc') }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================== EQUIPMENT INTEGRATION ===================== -->
    <section class="relative py-10 sm:py-14 bg-gradient-to-r from-primary-600 via-primary-700 to-primary-800 overflow-hidden">
      <!-- Decorative pattern -->
      <div class="absolute inset-0 opacity-[0.07]" style="background-image: linear-gradient(rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 32px 32px;"></div>
      <div class="absolute -top-20 -start-20 w-80 h-80 bg-white/5 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-20 -end-20 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>

      <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row items-center gap-5 sm:gap-7 text-center sm:text-start">
          <!-- Icon block with floating dots -->
          <div class="relative shrink-0">
            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white/15 backdrop-blur-md ring-1 ring-white/30 flex items-center justify-center">
              <svg class="w-7 h-7 sm:w-8 sm:h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
              </svg>
            </div>
            <span class="absolute -top-1 -end-1 w-3 h-3 rounded-full bg-success-400 ring-2 ring-primary-700 animate-pulse"></span>
          </div>

          <div class="flex-1">
            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-white/15 border border-white/20 rounded-full mb-2">
              <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-white">{{ t('equipment_integration_badge') || 'NEW' }}</span>
            </div>
            <h3 class="text-lg sm:text-xl lg:text-2xl font-bold text-white mb-1 leading-tight">{{ t('equipment_integration') }}</h3>
            <p class="text-xs sm:text-sm lg:text-base text-primary-100 max-w-2xl">{{ t('equipment_integration_desc') }}</p>
          </div>

          <!-- Tech pills -->
          <div class="hidden lg:flex items-center gap-2 shrink-0">
            <span class="px-3 py-1.5 bg-white/10 border border-white/20 rounded-full text-[11px] font-bold text-white">RS-232</span>
            <span class="px-3 py-1.5 bg-white/10 border border-white/20 rounded-full text-[11px] font-bold text-white">TCP/IP</span>
            <span class="px-3 py-1.5 bg-white/10 border border-white/20 rounded-full text-[11px] font-bold text-white">ASTM</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================== HOW IT WORKS ===================== -->
    <section id="how-it-works" class="py-16 sm:py-20 lg:py-28 bg-white relative overflow-hidden">
      <div class="absolute top-1/2 start-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-primary-50/40 rounded-full blur-3xl"></div>
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center max-w-2xl mx-auto mb-10 sm:mb-14 lg:mb-16">
          <div class="inline-flex items-center gap-2 px-3 sm:px-4 py-1 sm:py-1.5 bg-primary-50 border border-primary-200 rounded-full mb-4 sm:mb-6">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
            </svg>
            <span class="text-[11px] sm:text-sm font-semibold text-primary-700 uppercase tracking-wide">{{ t('how_badge') }}</span>
          </div>
          <h2 class="text-2xl sm:text-3xl lg:text-5xl font-bold text-slate-900 mb-3 sm:mb-4 leading-tight tracking-tight">
            {{ t('how_title') }}
            <span class="bg-gradient-to-r from-primary-600 to-primary-400 bg-clip-text text-transparent">{{ t('how_title_highlight') }}</span>
          </h2>
          <p class="text-sm sm:text-base lg:text-lg text-slate-500">{{ t('how_description') }}</p>
        </div>

        <!-- Steps -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 lg:gap-8 relative">
          <!-- Dashed connector (desktop only) -->
          <svg class="hidden md:block absolute top-8 start-[16%] end-[16%] w-2/3 mx-auto h-3 pointer-events-none" preserveAspectRatio="none" viewBox="0 0 800 12">
            <line x1="0" y1="6" x2="800" y2="6" stroke="var(--color-primary-300)" stroke-width="2" stroke-dasharray="6 6" stroke-linecap="round" />
          </svg>

          <!-- Step 1 -->
          <div class="relative">
            <!-- Floating step number -->
            <div class="relative w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white text-primary-600 flex items-center justify-center mx-auto mb-4 sm:mb-6 text-xl sm:text-2xl font-bold border-2 border-primary-200 shadow-lg shadow-primary-500/10 z-10">
              <span class="absolute -top-1 -end-1 w-5 h-5 rounded-full bg-primary-500 text-white text-[10px] font-bold flex items-center justify-center shadow-md">1</span>
              <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
              </svg>
            </div>
            <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 hover:border-primary-300 hover:shadow-xl hover:shadow-primary-500/10 transition-all text-center">
              <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-1.5 sm:mb-2">{{ t('how_step_1_title') }}</h3>
              <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">{{ t('how_step_1_desc') }}</p>
            </div>
          </div>

          <!-- Step 2 — featured -->
          <div class="relative">
            <div class="relative w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-600 text-white flex items-center justify-center mx-auto mb-4 sm:mb-6 text-xl sm:text-2xl font-bold shadow-2xl shadow-primary-500/40 z-10">
              <span class="absolute -top-1 -end-1 w-5 h-5 rounded-full bg-white text-primary-600 text-[10px] font-bold flex items-center justify-center shadow-md">2</span>
              <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
              </svg>
            </div>
            <div class="bg-gradient-to-br from-slate-900 to-slate-950 rounded-2xl p-5 sm:p-6 border border-slate-800 ring-1 ring-primary-500/30 shadow-2xl shadow-primary-500/20 text-center md:-translate-y-2">
              <h3 class="text-base sm:text-lg font-bold text-white mb-1.5 sm:mb-2">{{ t('how_step_2_title') }}</h3>
              <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">{{ t('how_step_2_desc') }}</p>
            </div>
          </div>

          <!-- Step 3 -->
          <div class="relative">
            <div class="relative w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white text-primary-600 flex items-center justify-center mx-auto mb-4 sm:mb-6 text-xl sm:text-2xl font-bold border-2 border-primary-200 shadow-lg shadow-primary-500/10 z-10">
              <span class="absolute -top-1 -end-1 w-5 h-5 rounded-full bg-primary-500 text-white text-[10px] font-bold flex items-center justify-center shadow-md">3</span>
              <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
              </svg>
            </div>
            <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 hover:border-primary-300 hover:shadow-xl hover:shadow-primary-500/10 transition-all text-center">
              <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-1.5 sm:mb-2">{{ t('how_step_3_title') }}</h3>
              <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">{{ t('how_step_3_desc') }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================== YOUTUBE CHANNEL ===================== -->
    <section class="relative py-12 sm:py-16 bg-slate-950 overflow-hidden">
      <!-- Lab grid pattern -->
      <div class="absolute inset-0 opacity-[0.05]" style="background-image: linear-gradient(rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 32px 32px;"></div>
      <div class="absolute inset-0" style="background: radial-gradient(ellipse at center, rgba(239,68,68,0.12), transparent 60%);"></div>

      <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-br from-slate-900 via-slate-900 to-[#1a0808] rounded-2xl sm:rounded-3xl border border-slate-800 p-6 sm:p-8 lg:p-10 overflow-hidden relative">
          <!-- Decorative red blob -->
          <div class="absolute -top-20 -end-20 w-72 h-72 bg-red-600/15 rounded-full blur-3xl"></div>

          <div class="relative flex flex-col sm:flex-row items-center gap-5 sm:gap-7 text-center sm:text-start">
            <!-- Authentic YouTube play button -->
            <div class="relative shrink-0 group">
              <div class="absolute inset-0 bg-red-600/50 rounded-2xl blur-xl group-hover:bg-red-600/70 transition-all"></div>
              <div class="relative w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-[#FF0000] flex items-center justify-center shadow-2xl shadow-red-600/50 border border-red-500/60">
                <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white ms-0.5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z" />
                </svg>
              </div>
            </div>

            <div class="flex-1">
              <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-red-600/15 border border-red-500/40 rounded-full mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-red-400">{{ t('youtube_label') }}</span>
              </div>
              <h3 class="text-lg sm:text-xl lg:text-2xl font-bold text-white mb-1 leading-tight">{{ t('youtube_title') }}</h3>
              <p class="text-xs sm:text-sm lg:text-base text-slate-400 max-w-2xl">{{ t('youtube_desc') }}</p>
            </div>

            <a
              href="https://www.youtube.com/@widesight1456"
              target="_blank"
              rel="noopener noreferrer"
              class="group inline-flex items-center gap-2 px-5 sm:px-6 py-3 sm:py-3.5 bg-[#FF0000] hover:bg-red-600 text-white font-bold text-xs sm:text-sm rounded-xl sm:rounded-2xl transition-all shadow-xl shadow-red-600/40 hover:shadow-red-600/60 hover:-translate-y-0.5 shrink-0"
            >
              <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
              </svg>
              {{ t('youtube_cta') }}
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================== TESTIMONIALS ===================== -->
    <section id="testimonials" class="py-16 sm:py-20 lg:py-28 bg-slate-50 relative overflow-hidden">
      <div class="absolute top-1/2 start-0 w-72 h-72 bg-primary-100/40 rounded-full blur-3xl -translate-y-1/2"></div>
      <div class="absolute bottom-0 end-0 w-96 h-96 bg-primary-50/60 rounded-full blur-3xl"></div>
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center max-w-2xl mx-auto mb-10 sm:mb-14 lg:mb-16">
          <div class="inline-flex items-center gap-2 px-3 sm:px-4 py-1 sm:py-1.5 bg-primary-50 border border-primary-200 rounded-full mb-4 sm:mb-6">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
            <span class="text-[11px] sm:text-sm font-semibold text-primary-700 uppercase tracking-wide">{{ t('testimonials_badge') }}</span>
          </div>
          <h2 class="text-2xl sm:text-3xl lg:text-5xl font-bold text-slate-900 mb-3 sm:mb-4 leading-tight tracking-tight">
            {{ t('testimonials_title') }}
            <span class="bg-gradient-to-r from-primary-600 to-primary-400 bg-clip-text text-transparent">{{ t('testimonials_title_highlight') }}</span>
          </h2>
          <p class="text-sm sm:text-base lg:text-lg text-slate-500">{{ t('testimonials_description') }}</p>
        </div>

        <!-- Trust badge row above grid -->
        <div class="flex flex-wrap items-center justify-center gap-x-4 sm:gap-x-8 gap-y-2 mb-8 sm:mb-12 text-xs">
          <div class="flex items-center gap-1.5">
            <div class="flex">
              <svg v-for="i in 5" :key="'top-' + i" class="w-3.5 h-3.5 text-warning-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </div>
            <span class="font-bold text-slate-700">4.9 / 5.0</span>
            <span class="text-slate-500">{{ t('testimonials_rating_label') || '· based on 500+ reviews' }}</span>
          </div>
        </div>

        <!-- Testimonials Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
          <!-- Testimonial 1 -->
          <div class="group relative bg-white rounded-2xl p-6 sm:p-7 border border-slate-200 hover:border-primary-300 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-0.5 transition-all overflow-hidden">
            <div class="absolute -top-12 -end-12 w-40 h-40 bg-primary-50 rounded-full blur-3xl group-hover:bg-primary-100 transition-all"></div>
            <div class="relative">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-0.5">
                  <svg v-for="i in 5" :key="'t1-' + i" class="w-4 h-4 text-warning-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                </div>
                <svg class="w-7 h-7 text-primary-100" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" /></svg>
              </div>
              <p class="text-slate-700 mb-6 leading-relaxed text-sm sm:text-base">"{{ t('testimonial_1_text') }}"</p>
              <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-xs shadow-lg shadow-primary-500/30">AR</div>
                <div class="flex-1">
                  <p class="font-bold text-slate-900 text-sm leading-tight">{{ t('testimonial_1_name') }}</p>
                  <p class="text-[11px] text-slate-500">{{ t('testimonial_1_role') }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Testimonial 2 — Featured Dark -->
          <div class="group relative bg-gradient-to-br from-slate-900 via-slate-900 to-primary-950 rounded-2xl p-6 sm:p-7 border border-slate-800 ring-1 ring-primary-500/30 shadow-2xl shadow-primary-500/20 lg:-translate-y-2 overflow-hidden">
            <div class="absolute -top-16 -end-16 w-56 h-56 bg-primary-500/15 rounded-full blur-3xl"></div>
            <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 28px 28px;"></div>

            <div class="relative">
              <!-- Featured pill -->
              <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-primary-500/15 border border-primary-500/30 rounded-full mb-4">
                <span class="w-1.5 h-1.5 rounded-full bg-primary-400 animate-pulse"></span>
                <span class="text-[9px] font-bold uppercase tracking-wider text-primary-300">{{ t('testimonials_featured') || 'FEATURED' }}</span>
              </div>

              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-0.5">
                  <svg v-for="i in 5" :key="'t2-' + i" class="w-4 h-4 text-warning-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                </div>
                <svg class="w-8 h-8 text-primary-500/30" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" /></svg>
              </div>
              <p class="text-white/95 mb-6 leading-relaxed text-sm sm:text-base font-medium">"{{ t('testimonial_2_text') }}"</p>
              <div class="flex items-center gap-3 pt-4 border-t border-white/10">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-xs shadow-lg shadow-primary-500/40 ring-2 ring-primary-500/30">SH</div>
                <div class="flex-1">
                  <p class="font-bold text-white text-sm leading-tight">{{ t('testimonial_2_name') }}</p>
                  <p class="text-[11px] text-slate-400">{{ t('testimonial_2_role') }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Testimonial 3 -->
          <div class="group relative bg-white rounded-2xl p-6 sm:p-7 border border-slate-200 hover:border-primary-300 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-0.5 transition-all overflow-hidden">
            <div class="absolute -top-12 -end-12 w-40 h-40 bg-primary-50 rounded-full blur-3xl group-hover:bg-primary-100 transition-all"></div>
            <div class="relative">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-0.5">
                  <svg v-for="i in 5" :key="'t3-' + i" class="w-4 h-4 text-warning-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                </div>
                <svg class="w-7 h-7 text-primary-100" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" /></svg>
              </div>
              <p class="text-slate-700 mb-6 leading-relaxed text-sm sm:text-base">"{{ t('testimonial_3_text') }}"</p>
              <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-xs shadow-lg shadow-primary-500/30">MA</div>
                <div class="flex-1">
                  <p class="font-bold text-slate-900 text-sm leading-tight">{{ t('testimonial_3_name') }}</p>
                  <p class="text-[11px] text-slate-500">{{ t('testimonial_3_role') }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================== PRICING SECTION ===================== -->
    <section id="pricing" class="py-16 sm:py-20 lg:py-28 bg-white relative overflow-hidden">
      <div class="absolute top-0 start-0 w-96 h-96 bg-primary-50/60 rounded-full blur-3xl"></div>
      <div class="absolute bottom-0 end-0 w-96 h-96 bg-primary-100/40 rounded-full blur-3xl"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center mb-10 sm:mb-14 lg:mb-16">
          <div class="inline-flex items-center gap-2 px-3 sm:px-4 py-1 sm:py-1.5 bg-primary-50 border border-primary-200 rounded-full mb-4 sm:mb-6">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-[11px] sm:text-sm font-semibold text-primary-700 uppercase tracking-wide">{{ t('pricing_badge') }}</span>
          </div>
          <h2 class="text-2xl sm:text-3xl lg:text-5xl font-bold text-slate-900 mb-3 sm:mb-4 leading-tight tracking-tight">
            {{ t('pricing_title') }}
            <span class="bg-gradient-to-r from-primary-600 to-primary-400 bg-clip-text text-transparent">{{ t('pricing_title_highlight') }}</span>
          </h2>
          <p class="text-sm sm:text-base lg:text-lg text-slate-500 max-w-2xl mx-auto">{{ t('pricing_description') }}</p>
        </div>

        <!-- Pricing Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5 mb-4 sm:mb-6">
          <!-- Plan card template repeated -->
          <div
            v-for="p in [1,2,3]"
            :key="'plan-' + p"
            class="group relative bg-white rounded-2xl p-5 sm:p-6 lg:p-7 border border-slate-200 hover:border-primary-300 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-0.5 transition-all overflow-hidden"
          >
            <div class="absolute -top-12 -end-12 w-40 h-40 bg-primary-50 rounded-full blur-3xl group-hover:bg-primary-100 transition-all"></div>
            <div class="relative">
              <div class="flex items-center gap-2 mb-1">
                <div class="w-8 h-8 rounded-lg bg-primary-50 border border-primary-100 flex items-center justify-center">
                  <span class="text-sm font-bold text-primary-600">{{ p }}</span>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-slate-900">{{ t(`pricing_plan_${p}_name`) }}</h3>
              </div>
              <p class="text-xs text-slate-500 mb-5 ms-10">{{ t(`pricing_plan_${p}_users`) }}</p>

              <div class="mb-5 pb-5 border-b border-slate-100">
                <div class="flex items-baseline gap-1">
                  <span class="text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight">{{ t(`pricing_plan_${p}_monthly`) }}</span>
                  <span class="text-xs text-slate-500">{{ t('pricing_currency') }}{{ t('pricing_per_month') }}</span>
                </div>
                <div class="inline-flex items-center gap-1.5 mt-2 px-2 py-0.5 bg-success-50 border border-success-100 rounded-full">
                  <svg class="w-3 h-3 text-success-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <span class="text-[10px] font-bold text-success-700">{{ t(`pricing_plan_${p}_annual`) }} {{ t('pricing_currency') }}{{ t('pricing_per_year') }}</span>
                </div>
              </div>

              <ul class="space-y-2.5 mb-6">
                <li v-for="f in 4" :key="f" class="flex items-start gap-2">
                  <div class="w-4 h-4 rounded-full bg-primary-100 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-2.5 h-2.5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  </div>
                  <span class="text-xs sm:text-sm text-slate-600 leading-snug">{{ t(`pricing_plan_${p}_f${f}`) }}</span>
                </li>
              </ul>
              <button
                @click="goToRegister"
                class="w-full py-2.5 sm:py-3 rounded-xl font-bold text-sm border-2 border-slate-200 text-slate-700 hover:border-primary-500 hover:text-primary-600 hover:bg-primary-50 transition-all"
              >
                {{ t(`pricing_plan_${p}_cta`) }}
              </button>
            </div>
          </div>

          <!-- Plan 4 (Most Popular) — Featured dark card -->
          <div class="relative bg-gradient-to-br from-slate-900 via-slate-900 to-primary-950 rounded-2xl p-5 sm:p-6 lg:p-7 border border-slate-800 ring-1 ring-primary-500/30 shadow-2xl shadow-primary-500/30 lg:-translate-y-3 overflow-hidden">
            <!-- Glow + grid -->
            <div class="absolute -top-20 -end-20 w-56 h-56 bg-primary-500/15 rounded-full blur-3xl"></div>
            <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 28px 28px;"></div>

            <div class="relative">
              <div class="flex items-center gap-2 mb-1">
                <div class="w-8 h-8 rounded-lg bg-primary-500/20 border border-primary-500/40 flex items-center justify-center">
                  <span class="text-sm font-bold text-primary-300">4</span>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-white">{{ t('pricing_plan_4_name') }}</h3>
              </div>
              <p class="text-xs text-slate-400 mb-5 ms-10">{{ t('pricing_plan_4_users') }}</p>

              <div class="mb-5 pb-5 border-b border-white/10">
                <div class="flex items-baseline gap-1">
                  <span class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-primary-300 to-primary-400 bg-clip-text text-transparent tracking-tight">{{ t('pricing_plan_4_monthly') }}</span>
                  <span class="text-xs text-slate-400">{{ t('pricing_currency') }}{{ t('pricing_per_month') }}</span>
                </div>
                <div class="inline-flex items-center gap-1.5 mt-2 px-2 py-0.5 bg-primary-500/15 border border-primary-500/30 rounded-full">
                  <svg class="w-3 h-3 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <span class="text-[10px] font-bold text-primary-300">{{ t('pricing_plan_4_annual') }} {{ t('pricing_currency') }}{{ t('pricing_per_year') }}</span>
                </div>
              </div>

              <ul class="space-y-2.5 mb-6">
                <li v-for="f in 4" :key="'p4-' + f" class="flex items-start gap-2">
                  <div class="w-4 h-4 rounded-full bg-primary-500/20 border border-primary-500/40 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-2.5 h-2.5 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  </div>
                  <span class="text-xs sm:text-sm text-slate-300 leading-snug">{{ t(`pricing_plan_4_f${f}`) }}</span>
                </li>
              </ul>
              <button
                @click="goToRegister"
                class="w-full py-2.5 sm:py-3 rounded-xl font-bold text-sm bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-400 hover:to-primary-500 text-white transition-all shadow-xl shadow-primary-500/40 hover:shadow-primary-500/60"
              >
                {{ t('pricing_plan_4_cta') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Plan 5 (Enterprise) — Wide banner card -->
        <div class="relative bg-gradient-to-br from-slate-900 via-slate-900 to-primary-950 rounded-2xl sm:rounded-3xl p-5 sm:p-7 lg:p-10 border border-slate-800 overflow-hidden">
          <div class="absolute -top-20 -end-20 w-72 h-72 bg-primary-500/15 rounded-full blur-3xl"></div>
          <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 32px 32px;"></div>

          <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5 sm:gap-7">
            <div class="flex-1">
              <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-primary-500/15 border border-primary-500/30 rounded-full mb-3">
                <svg class="w-3 h-3 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-primary-300">{{ t('pricing_plan_5_name') }}</span>
              </div>
              <h3 class="text-lg sm:text-2xl lg:text-3xl font-bold text-white mb-2 leading-tight">{{ t('pricing_plan_5_desc') }}</h3>
              <p class="text-xs sm:text-sm text-primary-300 font-semibold">{{ t('pricing_plan_5_branch_note') }}</p>
            </div>
            <div class="flex flex-col items-start lg:items-end gap-4">
              <ul class="grid grid-cols-2 gap-x-4 gap-y-2">
                <li v-for="f in 4" :key="'p5-' + f" class="flex items-center gap-2">
                  <div class="w-4 h-4 rounded-full bg-primary-500/20 border border-primary-500/40 flex items-center justify-center shrink-0">
                    <svg class="w-2.5 h-2.5 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  </div>
                  <span class="text-xs sm:text-sm text-slate-300">{{ t(`pricing_plan_5_f${f}`) }}</span>
                </li>
              </ul>
              <button
                @click="scrollTo('contact')"
                class="group inline-flex items-center gap-2 px-6 sm:px-8 py-3 sm:py-3.5 rounded-xl font-bold text-sm bg-primary-500 hover:bg-primary-400 text-white transition-all shadow-xl shadow-primary-500/40 hover:shadow-primary-500/60 hover:-translate-y-0.5"
              >
                {{ t('pricing_plan_5_cta') }}
                <svg class="w-4 h-4 rtl:rotate-180 transition-transform group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================== FAQ SECTION ===================== -->
    <section id="faq" class="py-16 sm:py-20 lg:py-28 bg-slate-50 relative overflow-hidden">
      <div class="absolute top-0 end-0 w-96 h-96 bg-primary-100/40 rounded-full blur-3xl"></div>
      <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="text-center mb-10 sm:mb-14 lg:mb-16">
          <div class="inline-flex items-center gap-2 px-3 sm:px-4 py-1 sm:py-1.5 bg-primary-50 border border-primary-200 rounded-full mb-4 sm:mb-6">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
            </svg>
            <span class="text-[11px] sm:text-sm font-semibold text-primary-700 uppercase tracking-wide">{{ t('faq_badge') }}</span>
          </div>
          <h2 class="text-2xl sm:text-3xl lg:text-5xl font-bold text-slate-900 mb-3 sm:mb-4 leading-tight tracking-tight">
            {{ t('faq_title') }}
            <span class="bg-gradient-to-r from-primary-600 to-primary-400 bg-clip-text text-transparent">{{ t('faq_title_highlight') }}</span>
          </h2>
          <p class="text-sm sm:text-base lg:text-lg text-slate-500">{{ t('faq_description') }}</p>
        </div>

        <!-- FAQ Items -->
        <div class="space-y-2 sm:space-y-3">
          <div
            v-for="i in 6"
            :key="i"
            class="group rounded-2xl border bg-white overflow-hidden transition-all duration-300"
            :class="openFaq === i ? 'border-primary-400 shadow-xl shadow-primary-500/15' : 'border-slate-200 hover:border-primary-200 shadow-sm'"
          >
            <button
              @click="toggleFaq(i)"
              class="w-full flex items-center justify-between p-4 sm:p-5 text-start gap-3 hover:bg-primary-50/30 transition-colors"
            >
              <div class="flex items-center gap-3 sm:gap-4 flex-1">
                <div
                  class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center shrink-0 text-sm font-bold transition-all"
                  :class="openFaq === i ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/40 scale-110' : 'bg-primary-50 text-primary-600 border border-primary-100'"
                >{{ i }}</div>
                <span class="font-bold text-slate-900 text-sm sm:text-base leading-snug">{{ t(`faq_${i}_q`) }}</span>
              </div>
              <div
                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center shrink-0 transition-all"
                :class="openFaq === i ? 'bg-primary-500 text-white rotate-180' : 'bg-slate-100 text-slate-500'"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </button>
            <Transition
              enter-active-class="transition-all duration-300 ease-out"
              enter-from-class="opacity-0 max-h-0"
              enter-to-class="opacity-100 max-h-96"
              leave-active-class="transition-all duration-200 ease-in"
              leave-from-class="opacity-100 max-h-96"
              leave-to-class="opacity-0 max-h-0"
            >
              <div v-if="openFaq === i" class="overflow-hidden">
                <div class="px-4 sm:px-5 pb-4 sm:pb-5 ps-[3.25rem] sm:ps-[4.25rem]">
                  <div class="border-s-2 border-primary-200 ps-4 py-1">
                    <p class="text-slate-600 leading-relaxed text-sm">{{ t(`faq_${i}_a`) }}</p>
                  </div>
                </div>
              </div>
            </Transition>
          </div>
        </div>

        <!-- Contact Support -->
        <div class="mt-10 sm:mt-14">
          <div class="relative bg-gradient-to-br from-slate-900 via-slate-900 to-primary-950 rounded-2xl border border-slate-800 p-6 sm:p-8 overflow-hidden">
            <div class="absolute -top-16 -end-16 w-56 h-56 bg-primary-500/15 rounded-full blur-3xl"></div>
            <div class="relative flex flex-col sm:flex-row items-center gap-4 sm:gap-5 text-center sm:text-start">
              <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-primary-500/20 border border-primary-500/40 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 sm:w-7 sm:h-7 text-primary-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                </svg>
              </div>
              <div class="flex-1">
                <p class="text-white font-bold text-sm sm:text-base mb-0.5">{{ t('didnt_find_answer') }}</p>
                <p class="text-slate-400 text-xs sm:text-sm">{{ t('faq_support_subtitle') || 'Our team is ready to help' }}</p>
              </div>
              <button class="group inline-flex items-center gap-2 px-5 sm:px-6 py-3 bg-primary-500 hover:bg-primary-400 text-white font-bold rounded-xl transition-all text-xs sm:text-sm shadow-xl shadow-primary-500/40 hover:shadow-primary-500/60 hover:-translate-y-0.5 shrink-0">
                {{ t('contact_support') }}
                <svg class="w-4 h-4 rtl:rotate-180 transition-transform group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================== CTA SECTION ===================== -->
    <section class="relative py-16 sm:py-20 lg:py-28 bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 overflow-hidden">
      <!-- Lab grid + decorative blobs -->
      <div class="absolute inset-0 opacity-[0.08]" style="background-image: linear-gradient(rgba(255,255,255,0.7) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.7) 1px, transparent 1px); background-size: 40px 40px;"></div>
      <div class="absolute -top-32 -start-32 w-96 h-96 bg-white/10 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-32 -end-32 w-96 h-96 bg-primary-300/20 rounded-full blur-3xl"></div>

      <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-flex items-center gap-2 px-3 sm:px-4 py-1 sm:py-1.5 bg-white/15 border border-white/25 rounded-full mb-5 sm:mb-7 backdrop-blur-sm">
          <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
          </span>
          <span class="text-[11px] sm:text-sm font-bold text-white tracking-wide uppercase">{{ t('cta_badge') }}</span>
        </div>

        <h2 class="text-3xl sm:text-4xl lg:text-6xl font-bold text-white mb-4 sm:mb-6 leading-[1.1] tracking-tight">
          {{ t('cta_title') }}
          <br class="hidden sm:block">
          <span class="text-primary-100">{{ t('cta_title_highlight') }}</span>
        </h2>
        <p class="text-sm sm:text-base lg:text-lg text-primary-50/90 mb-8 sm:mb-10 max-w-2xl mx-auto leading-relaxed">{{ t('cta_description') }}</p>

        <!-- CTA Buttons -->
        <div class="flex flex-row items-center justify-center gap-2 sm:gap-3 mb-8 sm:mb-12">
          <button
            @click="goToRegister"
            class="group relative px-5 sm:px-8 py-3 sm:py-4 bg-white hover:bg-primary-50 text-primary-700 font-bold rounded-xl sm:rounded-2xl transition-all shadow-2xl shadow-black/25 hover:shadow-black/40 hover:-translate-y-0.5 flex items-center justify-center gap-2 text-xs sm:text-base"
          >
            {{ t('cta_primary') }}
            <svg class="w-4 h-4 sm:w-5 sm:h-5 rtl:rotate-180 transition-transform group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
          </button>
          <button class="group px-5 sm:px-8 py-3 sm:py-4 bg-white/10 hover:bg-white/20 text-white font-semibold rounded-xl sm:rounded-2xl transition-all border-2 border-white/30 hover:border-white/60 flex items-center justify-center gap-2 text-xs sm:text-base backdrop-blur-sm">
            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            {{ t('cta_secondary') }}
          </button>
        </div>

        <!-- Features -->
        <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center justify-center gap-x-5 sm:gap-x-8 gap-y-3">
          <div v-for="i in 4" :key="'cta-f-' + i" class="flex items-center gap-2 text-white">
            <div class="w-5 h-5 rounded-full bg-white/20 border border-white/40 flex items-center justify-center shrink-0">
              <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            </div>
            <span class="text-[11px] sm:text-sm font-semibold">{{ t(`cta_feature_${i}`) }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================== FOOTER ===================== -->
    <footer class="relative bg-slate-950 overflow-hidden">

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 lg:py-16">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-6 sm:gap-8 lg:gap-12">
          <!-- Brand -->
          <div class="col-span-2">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-500/40 ring-1 ring-white/20 overflow-hidden">
                <img src="/lad_logo.png" alt="Logo" class="w-7 h-7 sm:w-8 sm:h-8 object-contain relative z-10" />
              </div>
              <div class="leading-tight">
                <span class="text-base sm:text-lg font-bold text-white tracking-tight">{{ t('nav_brand') }}</span>
                <p class="text-[10px] sm:text-xs text-slate-400 font-medium">{{ t('nav_tagline') }}</p>
              </div>
            </div>
            <p class="text-slate-400 mb-5 max-w-xs text-xs sm:text-sm leading-relaxed hidden sm:block">{{ t('footer_description') }}</p>

            <!-- Trust badges -->
            <div class="flex flex-wrap items-center gap-2 mb-5">
              <div class="inline-flex items-center gap-1.5 px-2 py-1 bg-success-500/10 border border-success-500/20 rounded-full">
                <svg class="w-3 h-3 text-success-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3z"/></svg>
                <span class="text-[10px] font-bold text-success-300">ISO 15189</span>
              </div>
              <div class="inline-flex items-center gap-1.5 px-2 py-1 bg-primary-500/10 border border-primary-500/20 rounded-full">
                <svg class="w-3 h-3 text-primary-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3z"/></svg>
                <span class="text-[10px] font-bold text-primary-300">HIPAA</span>
              </div>
            </div>

            <!-- Social -->
            <div class="flex items-center gap-2">
              <a href="#" class="w-9 h-9 rounded-xl bg-white/[0.04] hover:bg-primary-500/15 border border-white/10 hover:border-primary-500/40 flex items-center justify-center text-slate-400 hover:text-primary-300 transition-all">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
              </a>
              <a href="#" class="w-9 h-9 rounded-xl bg-white/[0.04] hover:bg-primary-500/15 border border-white/10 hover:border-primary-500/40 flex items-center justify-center text-slate-400 hover:text-primary-300 transition-all">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
              </a>
              <a href="#" class="w-9 h-9 rounded-xl bg-white/[0.04] hover:bg-primary-500/15 border border-white/10 hover:border-primary-500/40 flex items-center justify-center text-slate-400 hover:text-primary-300 transition-all">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>
              </a>
              <a href="#" class="w-9 h-9 rounded-xl bg-white/[0.04] hover:bg-primary-500/15 border border-white/10 hover:border-primary-500/40 flex items-center justify-center text-slate-400 hover:text-primary-300 transition-all">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
              </a>
            </div>
          </div>

          <!-- Product -->
          <div>
            <h4 class="font-bold text-white mb-3 sm:mb-4 text-xs sm:text-sm uppercase tracking-wider">{{ t('footer_product') }}</h4>
            <ul class="space-y-2 sm:space-y-2.5">
              <li><a href="#features" @click.prevent="scrollTo('features')" class="group flex items-center gap-1.5 text-slate-400 hover:text-primary-300 transition-colors text-xs sm:text-sm cursor-pointer">
                <span class="w-0 group-hover:w-2 h-px bg-primary-400 transition-all"></span>
                {{ t('footer_features') }}
              </a></li>
              <li><a href="#pricing" @click.prevent="scrollTo('pricing')" class="group flex items-center gap-1.5 text-slate-400 hover:text-primary-300 transition-colors text-xs sm:text-sm cursor-pointer">
                <span class="w-0 group-hover:w-2 h-px bg-primary-400 transition-all"></span>
                {{ t('footer_pricing') }}
              </a></li>
              <li><a href="#" class="group flex items-center gap-1.5 text-slate-400 hover:text-primary-300 transition-colors text-xs sm:text-sm">
                <span class="w-0 group-hover:w-2 h-px bg-primary-400 transition-all"></span>
                {{ t('footer_updates') }}
              </a></li>
            </ul>
          </div>

          <!-- Company -->
          <div>
            <h4 class="font-bold text-white mb-3 sm:mb-4 text-xs sm:text-sm uppercase tracking-wider">{{ t('footer_company') }}</h4>
            <ul class="space-y-2 sm:space-y-2.5">
              <li><a href="#" class="group flex items-center gap-1.5 text-slate-400 hover:text-primary-300 transition-colors text-xs sm:text-sm">
                <span class="w-0 group-hover:w-2 h-px bg-primary-400 transition-all"></span>
                {{ t('footer_about') }}
              </a></li>
              <li><a href="#" class="group flex items-center gap-1.5 text-slate-400 hover:text-primary-300 transition-colors text-xs sm:text-sm">
                <span class="w-0 group-hover:w-2 h-px bg-primary-400 transition-all"></span>
                {{ t('footer_careers') }}
              </a></li>
              <li><a href="#" class="group flex items-center gap-1.5 text-slate-400 hover:text-primary-300 transition-colors text-xs sm:text-sm">
                <span class="w-0 group-hover:w-2 h-px bg-primary-400 transition-all"></span>
                {{ t('footer_contact') }}
              </a></li>
            </ul>
          </div>

          <!-- Resources -->
          <div class="hidden sm:block">
            <h4 class="font-bold text-white mb-3 sm:mb-4 text-xs sm:text-sm uppercase tracking-wider">{{ t('footer_resources') }}</h4>
            <ul class="space-y-2 sm:space-y-2.5">
              <li><a href="#" class="group flex items-center gap-1.5 text-slate-400 hover:text-primary-300 transition-colors text-xs sm:text-sm">
                <span class="w-0 group-hover:w-2 h-px bg-primary-400 transition-all"></span>
                {{ t('footer_docs') }}
              </a></li>
              <li><a href="#" class="group flex items-center gap-1.5 text-slate-400 hover:text-primary-300 transition-colors text-xs sm:text-sm">
                <span class="w-0 group-hover:w-2 h-px bg-primary-400 transition-all"></span>
                {{ t('footer_help') }}
              </a></li>
              <li><a href="#" class="group flex items-center gap-1.5 text-slate-400 hover:text-primary-300 transition-colors text-xs sm:text-sm">
                <span class="w-0 group-hover:w-2 h-px bg-primary-400 transition-all"></span>
                {{ t('footer_api') }}
              </a></li>
            </ul>
          </div>

          <!-- Legal -->
          <div class="hidden sm:block">
            <h4 class="font-bold text-white mb-3 sm:mb-4 text-xs sm:text-sm uppercase tracking-wider">{{ t('footer_legal') }}</h4>
            <ul class="space-y-2 sm:space-y-2.5">
              <li><a href="#" class="group flex items-center gap-1.5 text-slate-400 hover:text-primary-300 transition-colors text-xs sm:text-sm">
                <span class="w-0 group-hover:w-2 h-px bg-primary-400 transition-all"></span>
                {{ t('footer_privacy') }}
              </a></li>
              <li><a href="#" class="group flex items-center gap-1.5 text-slate-400 hover:text-primary-300 transition-colors text-xs sm:text-sm">
                <span class="w-0 group-hover:w-2 h-px bg-primary-400 transition-all"></span>
                {{ t('footer_terms') }}
              </a></li>
            </ul>
          </div>
        </div>

        <!-- Bottom bar -->
        <div class="mt-10 sm:mt-12 pt-6 border-t border-slate-800/60 flex flex-col sm:flex-row items-center justify-between gap-3">
          <p class="text-slate-500 text-[11px] sm:text-xs">
            &copy; {{ new Date().getFullYear() }} <span class="font-bold text-slate-400">{{ t('nav_brand') }}</span>. {{ t('footer_rights') }}
          </p>
          <p class="text-slate-500 text-[11px] sm:text-xs flex items-center gap-1.5">
            {{ t('footer_made_with') }}
            <svg class="w-3.5 h-3.5 text-primary-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
            </svg>
            {{ t('footer_for') }}
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-10px); }
}
@keyframes float-delayed {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-8px); }
}
.animate-float {
  animation: float 4s ease-in-out infinite;
}
.animate-float-delayed {
  animation: float-delayed 5s ease-in-out infinite;
  animation-delay: 1s;
}
/* Hero ticker transition */
.ticker-enter-active, .ticker-leave-active { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
.ticker-enter-from { opacity: 0; transform: translateY(8px); }
.ticker-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
