var CMSInterwaly = {}; 	//interwaly odświeżania okien

(function($)
{
	//dodaje rozszerzenie do jQuery, że odgrywa melodię z linku
    $.extend({playSound: function(){return $("<audio autoplay='autoplay' style='display:none;' controls='controls'><source src='"+arguments[0]+"' /></audio>").appendTo('body');}});

})(jQuery);

function CMSInit()
{
    if('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js');    //rejestracja nawigatora do apki PWA
    
    let windows = CMSGetParameterByName('CMSWindows');
    
    if(windows == null)
        $.ajax({url: "www/CMSStarter.php", success: function(e) {$("#CMSStarter").html(e);}});
    else
    {
        $('#CMSStarter').hide();
        CMSWindowsHide();
        try
        {
            JSON.parse(windows).forEach(w => CMSWindowShow(w[0], w[1], w[2] ?? {}));
        }
        catch(e) { console.log('CMSWindows parse error:', e, windows); }
        history.replaceState(null, '', window.location.pathname);
    }
}

function CMSReLoad()
{
		//funkcja przeładowuje podstrony po zalogowaniu
	$.ajax({url: "www/CMSUserbox.php", success: function(e) {$("#CMSUserbox").html(e);}});

	$.ajax({url: "www/CMSPlayground.php", success: function(e) {$("#CMSPlayground").html(e);}});

	CMSLoaderHide();
}

function CMSLogout()
{
		//funkcja wylogowuje

	$.ajax({url: "process/CMSLogout.ajax.php",
		success: function(result)
		{
//				console.log(result);
				CMSReLoad();	//whole site reload due to login
		}});

}

function CMSLoaderShow()
{
	//functions shows loader
	$("#CMSLoader").show();
//	console.log('show loader');
}

function CMSLoaderHide()
{
	//function hides loader
	$("#CMSLoader").hide();
//	console.log('hide loader');
}

function CMSWindowShow(window, page, args = {})
{
	CMSWindowHide('Over');		//schowanie OVr
	
	let url = `www/${page}.php`;
	const queryParams = new URLSearchParams(args).toString();

	if(queryParams)	url += '?' + queryParams;

	$.ajax({url: url, success: function(e) {$("#CMSWindow"+window).html(e);}});

	$("#CMSShadow").fadeIn(500);
	$("#CMSWindow"+window).fadeIn(500);
}

function CMSWindowHide(window)
{
	//function hides window
	$("#CMSWindow"+window).fadeOut(500);
}

function CMSWindowsHide()
{
		//function hides windows
	$("#CMSWindowCenter").fadeOut(500);
	$("#CMSWindowTop").fadeOut(500);
	$("#CMSWindowBottom").fadeOut(500);
	$("#CMSWindowLeft").fadeOut(500);
	$("#CMSWindowRight").fadeOut(500);
	$("#CMSWindowOver").fadeOut(500);
	$("#CMSShadow").fadeOut(500);
}

function CMSNoticeShow(html)
{
	//funkcja pokazuje komunikat na dole strony
	$("#CMSNotice").html(html);
	$("#CMSNotice").fadeIn(500).delay(3000).fadeOut(1500);
}

function CMSOrientuj()
{
    //jeśli strona odpalona pionowo to ma zmienić wygląd na pionowy
    if($(window).height()>$(window).width()) CMSReOrientuj('higher');
    else CMSReOrientuj('wider');
}

function CMSReOrientuj(kierunek='reOrientuj')
{	
    //zmienia orientację tel↔komp
    $.ajax({url: 'process/CMSUpdate.ajax.php?tryb='+kierunek, success: function(e) {console.log(e); CMSReLoad();}});
}

function CMSLoginGoogleZaloguj(wynik)
{
		//funkcja loguje googlem
	$.ajax({url: "process/login.ajax.php?typ=google&dane="+encodeURIComponent(wynik.credential), success: function(result)
	{
//		console.log("Google login success:", result);
		CMSReLoad();
	}});
}

function CMSLoginGoogleInicjuj(selector, CID)
{
    //funkcja inicjuje powstanie odpowiedniego guzika google w odpowiednim miejscu
    try
    {
        google.accounts.id.initialize({client_id: CID, callback: CMSLoginGoogleZaloguj});
        
        // TUTAJ dodajemy parametry odpowiedzialne za pełną szerokość:
        google.accounts.id.renderButton(
            document.querySelector(selector),   
            { 
                theme: "outline", 
                size: "large",
                width_type: "filled", // Informuje Google, że ma wypełnić przestrzeń
                width: "100%"         // Wymusza 100% szerokości
            } 
        );
    }
    catch(e) {console.log('Problem z załadowaniem guzika loginGoogle:', e);}
}

function CMSLoginGoogle(selector, CID)
{
		//funkcja obsługuje wstawienie guzika logowania google
	if (typeof google !== 'undefined' && google.accounts) CMSLoginGoogleInicjuj(selector, CID);
	else
	{
		window.addEventListener('load', function()
		{
			if (typeof google !== 'undefined' && google.accounts) CMSLoginGoogleInicjuj(selector, CID);
			else
			{
				var checkInterval = setInterval(function()
				{
					if (typeof google !== 'undefined' && google.accounts)
					{
						clearInterval(checkInterval);
						CMSLoginGoogleInicjuj(selector, CID);
					}
				}, 100);
				
				setTimeout(function(){clearInterval(checkInterval);}, 5000);
			}
		});
	}
}

function CMSGetParameterByName(name, url)
{
    // funkcja zwraca wartość konkretnego parametru z URL
    if (!url) url = window.location.href;
    name = name.replace(/[\[\]]/g, "\\$&");
    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, " "));
}

function CMSQueryStringToJSON(url)
{
	//Konwertuje cały query string na obiekt JSON z wszystkimi parametrami.
    if (url === '') return '';
    var pairs = (url || location.search).slice(1).split('&');
    var result = {};
    for (var idx in pairs) {
        var pair = pairs[idx].split('=');
        if (!!pair[0])
            result[pair[0].toLowerCase()] = decodeURIComponent(pair[1] || '');
    }
    return result;
}

function CMSAkcja(link, callback=null)
{
	// wstawi efekt z linku w div, po zakończeniu może wywołać callback
	$.ajax(
	{
		url: link,
		success: function(html)
		{
			if (typeof callback === 'function') {
				callback(html);
			}
		}
	});
}

function CMSDivAktualizuj(link, div, callback=null)
{
	// wstawi efekt z linku w div, po zakończeniu może wywołać callback
	$.ajax(
	{
		url: link,
		success: function(html)
		{
			$(div).html(html);
			if (typeof callback === 'function') {
				callback(html);
			}
		}
	});
}

function CMSDivAkcjaAktualizuj(wykonaj, link, div, callback=null, czyKomunikat=false)
{
	//wykona wykonaj, a potem wczyta wynik link do div, na koniec ewentualnie wykona callback i ewentualnie wynik przekaże też do komunikatu
    $.ajax({
        url: wykonaj,
        success: function(html)
        {
            CMSDivAktualizuj(link, div, function() {
                if (typeof callback === 'function') {
                    callback(html);
                }
                if (czyKomunikat === true) {
                    CMSNoticeShow(html);
                }
            });
        }
    });
}

function CMSWykresGeneruj(kontener, dane)
{
	//funkcja generuje wykres w kontenerze na podstawie danych
	
	function rysuj() {
		// Konwertuj stringi dat na Date
		if (dane.data?.datasets) {
			dane.data.datasets.forEach(d => {
				if (d.data?.[0]?.x && typeof d.data[0].x === 'string') {
					d.data = d.data.map(p => ({x: new Date(p.x), y: p.y}));
				}
			});
		}
		
		return new Chart(kontener, {type: 'line', data: dane.data, options: dane.options});
	}
	
	// Załaduj adapter tylko jeśli używamy osi 'time'
	const needsAdapter = Object.values(dane.options?.scales || {}).some(s => s.type === 'time');
	if (needsAdapter && !window._chartAdapter) {
		const s = document.createElement('script');
		s.src = 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3';
		s.onload = () => { window._chartAdapter = 1; rysuj(); };
		document.head.appendChild(s);
	} else {
		return rysuj();
	}
}

function CMSCzasKonwertuj(sourceTimezone, time) {
    return luxon.DateTime.fromISO(time, { zone: sourceTimezone })
        .toLocal()
        .toFormat('HH:mm:ss');
}

function CMSOknoInterwal(nazwa, czas)
{
	if (CMSInterwaly[nazwa]) clearInterval(CMSInterwaly[nazwa]);										//usun stary interwal o tej nazwie
	CMSDivAktualizuj('www/'+nazwa+'.ajax.php', '#'+nazwa);												//aktualizuj div
	CMSInterwaly[nazwa]=setInterval(() => CMSDivAktualizuj('www/'+nazwa+'.ajax.php', '#'+nazwa), czas);	//ustaw nowy interwal

	return CMSInterwaly[nazwa];
}


